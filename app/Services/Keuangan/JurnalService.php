<?php

namespace App\Services\Keuangan;

use App\Models\Akun;
use App\Models\EntitasAkuntansi;
use App\Models\Jurnal;
use App\Models\JurnalBaris;
use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAkunPeriode;
use App\Services\PenomoranDokumen;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JurnalService
{
    public function buatDraft(array $data, array $baris): Jurnal
    {
        $this->validasiBaris($baris);

        return DB::transaction(function () use ($data, $baris) {
            $periode = $this->periodeUntukTanggal($data['entitas_id'], $data['tanggal']);

            $nomor = app(PenomoranDokumen::class)->berikutnya(
                'jurnal',
                $data['cabang_id'] ?? null,
                (int) date('Y', strtotime($data['tanggal'])),
                'JRN/{CABANG}/{TAHUN}/{SEQ:5}'
            );

            $jurnal = Jurnal::create([
                'entitas_id' => $data['entitas_id'],
                'cabang_id' => $data['cabang_id'] ?? null,
                'periode_id' => $periode->id,
                'nomor' => $nomor,
                'tanggal' => $data['tanggal'],
                'keterangan' => $data['keterangan'],
                'sumber_type' => $data['sumber_type'] ?? null,
                'sumber_id' => $data['sumber_id'] ?? null,
                'status' => 'draft',
                'dibuat_oleh' => $data['dibuat_oleh'],
            ]);

            $this->simpanBaris($jurnal->id, $baris);

            return $jurnal->fresh(['baris']);
        });
    }

    public function ubahDraft(Jurnal $jurnal, array $data, array $baris): Jurnal
    {
        $this->validasiBaris($baris);

        return DB::transaction(function () use ($jurnal, $data, $baris) {
            $terkunci = Jurnal::query()->lockForUpdate()->findOrFail($jurnal->id);
            if ($terkunci->status !== 'draft') {
                throw new RuntimeException('Hanya jurnal draft yang dapat diubah.');
            }

            $terkunci->update([
                'tanggal' => $data['tanggal'] ?? $terkunci->tanggal,
                'keterangan' => $data['keterangan'] ?? $terkunci->keterangan,
            ]);

            JurnalBaris::where('jurnal_id', $terkunci->id)->delete();
            $this->simpanBaris($terkunci->id, $baris);

            return $terkunci->fresh(['baris']);
        });
    }

    public function posting(Jurnal $jurnal, int $userId): Jurnal
    {
        return DB::transaction(function () use ($jurnal, $userId) {
            $terkunci = Jurnal::query()->lockForUpdate()->findOrFail($jurnal->id);

            if ($terkunci->status !== 'draft') {
                throw new RuntimeException('Hanya jurnal draft yang dapat diposting.');
            }

            $periode = PeriodeAkuntansi::query()->lockForUpdate()->findOrFail($terkunci->periode_id);
            if ($periode->status !== 'terbuka') {
                throw new RuntimeException('Periode akuntansi sudah ditutup.');
            }

            $totalDebit = $terkunci->baris()->sum('debit');
            $totalKredit = $terkunci->baris()->sum('kredit');
            if (bccomp((string) $totalDebit, (string) $totalKredit, 2) !== 0) {
                throw new RuntimeException('Total debit dan kredit tidak seimbang.');
            }

            $terkunci->update([
                'status' => 'posted',
                'diposting_oleh' => $userId,
                'diposting_pada' => now(),
            ]);

            return $terkunci->fresh(['baris']);
        });
    }

    public function void(Jurnal $jurnal, int $userId, string $alasan): Jurnal
    {
        return DB::transaction(function () use ($jurnal, $userId, $alasan) {
            $terkunci = Jurnal::query()->lockForUpdate()->findOrFail($jurnal->id);

            if ($terkunci->status !== 'posted') {
                throw new RuntimeException('Hanya jurnal posted yang dapat di-void.');
            }

            $barisPembalik = [];
            foreach ($terkunci->baris as $baris) {
                $barisPembalik[] = [
                    'akun_id' => $baris->akun_id,
                    'debit' => $baris->kredit,
                    'kredit' => $baris->debit,
                    'pusat_biaya_id' => $baris->pusat_biaya_id,
                    'keterangan' => 'Pembalik: ' . ($baris->keterangan ?? ''),
                ];
            }

            $jurnalPembalik = $this->buatDraft([
                'entitas_id' => $terkunci->entitas_id,
                'cabang_id' => $terkunci->cabang_id,
                'tanggal' => today()->toDateString(),
                'keterangan' => "Void jurnal {$terkunci->nomor}: {$alasan}",
                'sumber_type' => 'jurnal_pembalik',
                'sumber_id' => $terkunci->id,
                'dibuat_oleh' => $userId,
            ], $barisPembalik);

            $this->posting($jurnalPembalik, $userId);
            $terkunci->update(['status' => 'void']);

            return $terkunci->fresh();
        });
    }

    public function tutupPeriode(PeriodeAkuntansi $periode): PeriodeAkuntansi
    {
        return DB::transaction(function () use ($periode) {
            $terkunci = PeriodeAkuntansi::query()->lockForUpdate()->findOrFail($periode->id);

            if ($terkunci->status !== 'terbuka') {
                throw new RuntimeException('Periode sudah ditutup.');
            }

            $saldoList = JurnalBaris::query()
                ->join('jurnal', 'jurnal_baris.jurnal_id', '=', 'jurnal.id')
                ->where('jurnal.entitas_id', $terkunci->entitas_id)
                ->where('jurnal.periode_id', $terkunci->id)
                ->where('jurnal.status', 'posted')
                ->selectRaw('jurnal_baris.akun_id, SUM(jurnal_baris.debit) as debit, SUM(jurnal_baris.kredit) as kredit')
                ->groupBy('jurnal_baris.akun_id')
                ->get();

            foreach ($saldoList as $s) {
                $akun = Akun::find($s->akun_id);
                $saldoAkhir = $akun && $akun->saldo_normal === 'debit'
                    ? bcsub((string) $s->debit, (string) $s->kredit, 2)
                    : bcsub((string) $s->kredit, (string) $s->debit, 2);

                SaldoAkunPeriode::create([
                    'entitas_id' => $terkunci->entitas_id,
                    'akun_id' => $s->akun_id,
                    'periode_id' => $terkunci->id,
                    'debit' => $s->debit,
                    'kredit' => $s->kredit,
                    'saldo_akhir' => $saldoAkhir,
                ]);
            }

            $terkunci->update(['status' => 'ditutup']);

            return $terkunci;
        });
    }

    private function validasiBaris(array $baris): void
    {
        if (empty($baris)) {
            throw new RuntimeException('Jurnal harus memiliki minimal satu baris.');
        }

        $totalDebit = '0';
        $totalKredit = '0';

        foreach ($baris as $b) {
            $debit = $b['debit'] ?? 0;
            $kredit = $b['kredit'] ?? 0;

            if ($debit < 0 || $kredit < 0) {
                throw new RuntimeException('Nilai debit dan kredit tidak boleh negatif.');
            }

            $totalDebit = bcadd($totalDebit, (string) $debit, 2);
            $totalKredit = bcadd($totalKredit, (string) $kredit, 2);
        }

        if (bccomp($totalDebit, $totalKredit, 2) !== 0) {
            throw new RuntimeException('Total debit dan kredit tidak seimbang.');
        }
    }

    private function periodeUntukTanggal(int $entitasId, string $tanggal): PeriodeAkuntansi
    {
        $tahun = (int) date('Y', strtotime($tanggal));
        $bulan = (int) date('n', strtotime($tanggal));

        $periode = PeriodeAkuntansi::query()
            ->where('entitas_id', $entitasId)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->first();

        if (!$periode) {
            $periode = PeriodeAkuntansi::create([
                'entitas_id' => $entitasId,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'status' => 'terbuka',
            ]);
        }

        if ($periode->status !== 'terbuka') {
            throw new RuntimeException("Periode {$tahun}-{$bulan} sudah ditutup.");
        }

        return $periode;
    }

    private function simpanBaris(int $jurnalId, array $baris): void
    {
        foreach ($baris as $b) {
            JurnalBaris::create([
                'jurnal_id' => $jurnalId,
                'akun_id' => $b['akun_id'],
                'pusat_biaya_id' => $b['pusat_biaya_id'] ?? null,
                'debit' => $b['debit'] ?? 0,
                'kredit' => $b['kredit'] ?? 0,
                'keterangan' => $b['keterangan'] ?? null,
            ]);
        }
    }
}
