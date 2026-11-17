<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiPegawai;
use App\Models\JenisCuti;
use App\Models\Pegawai;
use App\Models\PengajuanCuti;
use App\Models\SaldoCuti;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan dan persetujuan cuti pegawai.
 * Setelah disetujui, otomatis menandai absensi_pegawai sebagai 'cuti' pada rentang tanggal.
 */
class CutiService
{
    /**
     * Buat pengajuan cuti (status diajukan).
     */
    public function ajukan(Pegawai $pegawai, JenisCuti $jenis, string $tanggalMulai, string $tanggalSelesai, int $jumlahHari, ?string $alasan = null, ?Pegawai $pengganti = null): PengajuanCuti
    {
        if (! $jenis->is_aktif) {
            throw new SdmException('Jenis cuti tidak aktif.');
        }

        if ($tanggalSelesai < $tanggalMulai) {
            throw new SdmException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        if ($jumlahHari < 1) {
            throw new SdmException('Jumlah hari cuti minimal 1.');
        }

        $penugasan = app(AbsensiService::class)->penugasanAktifPada($pegawai, $tanggalMulai);
        if ($penugasan === null) {
            throw new SdmException('Pegawai tidak memiliki penugasan aktif pada tanggal '.$tanggalMulai.'.');
        }

        // Cek pengajuan bertumpang tindih
        $tumpangTindih = PengajuanCuti::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereIn('status', ['diajukan', 'disetujui'])
            ->where('tanggal_mulai', '<=', $tanggalSelesai)
            ->where('tanggal_selesai', '>=', $tanggalMulai)
            ->exists();
        if ($tumpangTindih) {
            throw new SdmException('Sudah ada pengajuan cuti pada rentang tanggal tersebut.');
        }

        // Validasi saldo jika jenis cuti punya kuota
        if ($jenis->kuota_hari_tahunan !== null) {
            $tahun = (int) substr($tanggalMulai, 0, 4);
            $saldo = SaldoCuti::query()
                ->where('pegawai_id', $pegawai->id)
                ->where('jenis_cuti_id', $jenis->id)
                ->where('tahun', $tahun)
                ->first();

            if ($saldo === null) {
                // Auto-create saldo dari kuota jenis cuti
                $saldo = SaldoCuti::create([
                    'pegawai_id' => $pegawai->id,
                    'jenis_cuti_id' => $jenis->id,
                    'tahun' => $tahun,
                    'kuota' => $jenis->kuota_hari_tahunan,
                    'terpakai' => 0,
                ]);
            }

            $sisa = $saldo->kuota - $saldo->terpakai;
            if ($jumlahHari > $sisa) {
                throw new SdmException('Sisa cuti tidak mencukupi. Sisa: '.$sisa.' hari, diajukan: '.$jumlahHari.' hari.');
            }
        }

        return PengajuanCuti::create([
            'pegawai_id' => $pegawai->id,
            'cabang_id' => $penugasan->cabang_id,
            'jenis_cuti_id' => $jenis->id,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'jumlah_hari' => $jumlahHari,
            'alasan' => $alasan,
            'pengganti_id' => $pengganti?->id,
            'status' => 'diajukan',
        ]);
    }

    /**
     * Setujui pengajuan cuti.
     * - Potong saldo cuti (jika ada kuota).
     * - Tandai absensi_pegawai sebagai 'cuti' untuk setiap hari dalam rentang.
     */
    public function setujui(PengajuanCuti $pengajuan, User $penyetuju): PengajuanCuti
    {
        return DB::transaction(function () use ($pengajuan, $penyetuju) {
            $terkunci = PengajuanCuti::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($terkunci->status !== 'diajukan') {
                throw new SdmException('Pengajuan cuti sudah diproses.');
            }

            $terkunci->update([
                'status' => 'disetujui',
                'disetujui_oleh' => $penyetuju->id,
                'disetujui_pada' => now(),
            ]);

            // Potong saldo dengan lock + re-check
            $jenis = $terkunci->jenisCuti;
            if ($jenis->kuota_hari_tahunan !== null) {
                $tahun = (int) $terkunci->tanggal_mulai->format('Y');
                $saldo = SaldoCuti::query()
                    ->where('pegawai_id', $terkunci->pegawai_id)
                    ->where('jenis_cuti_id', $jenis->id)
                    ->where('tahun', $tahun)
                    ->lockForUpdate()
                    ->first();

                if ($saldo !== null) {
                    $sisa = $saldo->kuota - $saldo->terpakai;
                    if ($terkunci->jumlah_hari > $sisa) {
                        throw new SdmException('Sisa cuti tidak mencukupi saat persetujuan.');
                    }
                    $saldo->increment('terpakai', $terkunci->jumlah_hari);
                }
            }

            // Tandai absensi untuk setiap hari dalam rentang cuti
            $this->tandaiAbsensiCuti($terkunci);

            return $terkunci;
        });
    }

    /**
     * Tolak pengajuan cuti.
     */
    public function tolak(PengajuanCuti $pengajuan, User $penyetuju, ?string $alasanPenolakan = null): PengajuanCuti
    {
        return DB::transaction(function () use ($pengajuan, $penyetuju, $alasanPenolakan) {
            $terkunci = PengajuanCuti::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($terkunci->status !== 'diajukan') {
                throw new SdmException('Pengajuan cuti sudah diproses.');
            }

            $terkunci->update([
                'status' => 'ditolak',
                'disetujui_oleh' => $penyetuju->id,
                'disetujui_pada' => now(),
                'alasan_penolakan' => $alasanPenolakan,
            ]);

            return $terkunci;
        });
    }

    /**
     * Batalkan pengajuan cuti (oleh pegawai sendiri atau admin).
     * Jika sudah disetujui, kembalikan saldo dan hapus absensi cuti.
     */
    public function batalkan(PengajuanCuti $pengajuan): PengajuanCuti
    {
        return DB::transaction(function () use ($pengajuan) {
            $terkunci = PengajuanCuti::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($terkunci->status === 'dibatalkan') {
                throw new SdmException('Pengajuan cuti sudah dibatalkan.');
            }

            if ($terkunci->status === 'disetujui') {
                $jenis = $terkunci->jenisCuti;
                if ($jenis->kuota_hari_tahunan !== null) {
                    $tahun = (int) $terkunci->tanggal_mulai->format('Y');
                    $saldo = SaldoCuti::query()
                        ->where('pegawai_id', $terkunci->pegawai_id)
                        ->where('jenis_cuti_id', $jenis->id)
                        ->where('tahun', $tahun)
                        ->lockForUpdate()
                        ->first();

                    if ($saldo !== null) {
                        $saldo->decrement('terpakai', $terkunci->jumlah_hari);
                    }
                }

                // Hapus hanya absensi cuti milik pengajuan ini
                AbsensiPegawai::query()
                    ->where('pengajuan_cuti_id', $terkunci->id)
                    ->delete();
            }

            $terkunci->update(['status' => 'dibatalkan']);

            return $terkunci;
        });
    }

    /**
     * Tandai absensi_pegawai sebagai 'cuti' untuk setiap hari dalam rentang cuti.
     */
    private function tandaiAbsensiCuti(PengajuanCuti $pengajuan): void
    {
        $mulai = Carbon::parse($pengajuan->tanggal_mulai);
        $selesai = Carbon::parse($pengajuan->tanggal_selesai);
        $absensiSvc = app(AbsensiService::class);

        $tanggal = $mulai->copy();
        while ($tanggal->lte($selesai)) {
            $tanggalStr = $tanggal->toDateString();
            $penugasan = $absensiSvc->penugasanAktifPada($pengajuan->pegawai, $tanggalStr);

            if ($penugasan !== null) {
                $absensiSvc->catatManual($pengajuan->pegawai, $tanggalStr, [
                    'status' => 'cuti',
                    'keterangan' => 'Cuti '.$pengajuan->jenisCuti->nama.' (#'.$pengajuan->id.')',
                    'pengajuan_cuti_id' => $pengajuan->id,
                ], $pengajuan->disetujui_oleh);
            }

            $tanggal->addDay();
        }
    }
}
