<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\GajiPegawai;
use App\Models\KomponenGaji;
use App\Models\Pegawai;
use App\Models\PeriodeGaji;
use App\Models\SlipGaji;
use App\Models\SlipGajiRincian;
use Illuminate\Support\Facades\DB;

/**
 * Payroll: hitung gaji + potongan, buat slip dengan snapshot rincian.
 * Rincian slip tidak berubah bila komponen_gaji diubah setelahnya.
 */
class PayrollService
{
    /**
     * Tetapkan gaji pokok pegawai (riwayat; hanya satu aktif).
     */
    public function tetapkanGaji(Pegawai $pegawai, float $gajiPokok, string $tanggalBerlaku, ?int $skemaId = null, ?string $noSk = null): GajiPegawai
    {
        return DB::transaction(function () use ($pegawai, $gajiPokok, $tanggalBerlaku, $skemaId, $noSk) {
            // Nonaktifkan gaji aktif sebelumnya
            GajiPegawai::query()
                ->where('pegawai_id', $pegawai->id)
                ->where('status', 'aktif')
                ->update(['status' => 'berakhir']);

            return GajiPegawai::create([
                'pegawai_id' => $pegawai->id,
                'skema_id' => $skemaId,
                'gaji_pokok' => $gajiPokok,
                'tanggal_berlaku' => $tanggalBerlaku,
                'no_sk' => $noSk,
                'status' => 'aktif',
            ]);
        });
    }

    /**
     * Buat periode gaji.
     */
    public function buatPeriode(int $cabangId, int $tahun, int $bulan, string $tanggalMulai, string $tanggalSelesai): PeriodeGaji
    {
        return PeriodeGaji::create([
            'cabang_id' => $cabangId,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'status' => 'draft',
        ]);
    }

    /**
     * Hitung gaji untuk satu pegawai pada periode tertentu.
     * - Buat slip_gaji dengan status 'dihitung'.
     * - Simpan snapshot rincian (nama, tipe, nominal) dari komponen_gaji.
     */
    public function hitungGaji(PeriodeGaji $periode, Pegawai $pegawai): SlipGaji
    {
        if ($periode->status !== 'draft') {
            throw new SdmException('Periode gaji sudah diproses.');
        }

        $gajiPegawai = GajiPegawai::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('status', 'aktif')
            ->where('tanggal_berlaku', '<=', $periode->tanggal_selesai)
            ->orderByDesc('tanggal_berlaku')
            ->first();

        if ($gajiPegawai === null) {
            throw new SdmException('Pegawai tidak memiliki gaji pokok aktif.');
        }

        return DB::transaction(function () use ($periode, $pegawai, $gajiPegawai) {
            $slip = SlipGaji::create([
                'periode_id' => $periode->id,
                'pegawai_id' => $pegawai->id,
                'status' => 'draft',
            ]);

            $totalPendapatan = (float) $gajiPegawai->gaji_pokok;
            $totalPotongan = 0.0;

            // Gaji pokok sebagai pendapatan dasar
            SlipGajiRincian::create([
                'slip_id' => $slip->id,
                'nama' => 'Gaji Pokok',
                'tipe' => 'pendapatan',
                'nominal' => $gajiPegawai->gaji_pokok,
            ]);

            // Tambahkan komponen dari skema gaji (jika ada)
            if ($gajiPegawai->skema_id !== null) {
                $skemaKomponen = $gajiPegawai->skema->komponen;

                foreach ($skemaKomponen as $sk) {
                    $komponen = $sk->komponen;
                    $nominal = $this->hitungNominalKomponen($komponen, $sk, $gajiPegawai, $periode, $pegawai);

                    if ($komponen->tipe === 'pendapatan') {
                        $totalPendapatan += $nominal;
                    } else {
                        $totalPotongan += $nominal;
                    }

                    // Snapshot rincian
                    SlipGajiRincian::create([
                        'slip_id' => $slip->id,
                        'komponen_id' => $komponen->id,
                        'nama' => $komponen->nama,
                        'tipe' => $komponen->tipe,
                        'nominal' => $nominal,
                        'keterangan' => $sk->keterangan ?? null,
                    ]);
                }
            }

            $gajiBersih = $totalPendapatan - $totalPotongan;

            $slip->update([
                'total_pendapatan' => $totalPendapatan,
                'total_potongan' => $totalPotongan,
                'gaji_bersih' => $gajiBersih,
                'status' => 'dihitung',
            ]);

            return $slip;
        });
    }

    /**
     * Hitung nominal komponen berdasarkan cara_hitung.
     */
    private function hitungNominalKomponen(KomponenGaji $komponen, $skemaKomponen, GajiPegawai $gajiPegawai, PeriodeGaji $periode, Pegawai $pegawai): float
    {
        return match ($komponen->cara_hitung) {
            'tetap' => (float) ($skemaKomponen->nominal ?? 0),
            'persen' => (float) $gajiPegawai->gaji_pokok * ((float) ($skemaKomponen->persen ?? 0) / 100),
            'formula' => $this->hitungFormula($komponen, $gajiPegawai, $periode, $pegawai),
            'per_kehadiran' => $this->hitungPerKehadiran($komponen, $gajiPegawai, $periode, $pegawai),
            default => 0.0,
        };
    }

    /**
     * Hitung formula sederhana (contoh: gaji_pokok * persen).
     */
    private function hitungFormula(KomponenGaji $komponen, GajiPegawai $gajiPegawai, PeriodeGaji $periode, Pegawai $pegawai): float
    {
        $formula = $komponen->formula ?? [];
        $basis = $formula['basis'] ?? 'gaji_pokok';
        $persen = (float) ($formula['persen'] ?? 0);

        $nilai = match ($basis) {
            'gaji_pokok' => (float) $gajiPegawai->gaji_pokok,
            default => 0.0,
        };

        return $nilai * ($persen / 100);
    }

    /**
     * Hitung komponen berbasis kehadiran (contoh: tunjangan transport per hari hadir).
     */
    private function hitungPerKehadiran(KomponenGaji $komponen, GajiPegawai $gajiPegawai, PeriodeGaji $periode, Pegawai $pegawai): float
    {
        $formula = $komponen->formula ?? [];
        $perHari = (float) ($formula['per_hari'] ?? 0);

        $hariHadir = \App\Models\AbsensiPegawai::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [$periode->tanggal_mulai, $periode->tanggal_selesai])
            ->whereIn('status', ['hadir', 'terlambat'])
            ->count();

        return $hariHadir * $perHari;
    }

    /**
     * Setujui periode gaji (semua slip menjadi disetujui).
     */
    public function setujuiPeriode(PeriodeGaji $periode): PeriodeGaji
    {
        if ($periode->status !== 'dihitung') {
            throw new SdmException('Periode gaji belum dihitung.');
        }

        return DB::transaction(function () use ($periode) {
            $terkunci = PeriodeGaji::query()->lockForUpdate()->findOrFail($periode->id);
            if ($terkunci->status !== 'dihitung') {
                throw new SdmException('Periode gaji sudah disetujui atau belum dihitung.');
            }

            SlipGaji::query()
                ->where('periode_id', $terkunci->id)
                ->update(['status' => 'disetujui']);

            $terkunci->update(['status' => 'disetujui']);

            return $terkunci;
        });
    }

    /**
     * Tandai periode gaji sebagai dibayar.
     */
    public function bayarPeriode(PeriodeGaji $periode, string $tanggalBayar): PeriodeGaji
    {
        if ($periode->status !== 'disetujui') {
            throw new SdmException('Periode gaji belum disetujui.');
        }

        return DB::transaction(function () use ($periode, $tanggalBayar) {
            $terkunci = PeriodeGaji::query()->lockForUpdate()->findOrFail($periode->id);
            if ($terkunci->status !== 'disetujui') {
                throw new SdmException('Periode gaji sudah dibayar atau belum disetujui.');
            }

            SlipGaji::query()
                ->where('periode_id', $terkunci->id)
                ->update([
                    'status' => 'dibayar',
                    'tanggal_bayar' => $tanggalBayar,
                ]);

            $terkunci->update(['status' => 'dibayar']);

            return $terkunci;
        });
    }
}
