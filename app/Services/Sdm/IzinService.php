<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan dan persetujuan izin/sakit/dinas luar.
 * Setelah disetujui, otomatis menandai absensi_pegawai sesuai jenis izin.
 */
class IzinService
{
    /**
     * Buat pengajuan izin (status diajukan).
     */
    public function ajukan(Pegawai $pegawai, string $jenis, string $tanggalMulai, string $tanggalSelesai, ?string $alasan = null): PengajuanIzin
    {
        $penugasan = app(AbsensiService::class)->penugasanAktifPada($pegawai, $tanggalMulai);
        if ($penugasan === null) {
            throw new SdmException('Pegawai tidak memiliki penugasan aktif pada tanggal '.$tanggalMulai.'.');
        }

        return PengajuanIzin::create([
            'pegawai_id' => $pegawai->id,
            'cabang_id' => $penugasan->cabang_id,
            'jenis' => $jenis,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'alasan' => $alasan,
            'status' => 'diajukan',
        ]);
    }

    /**
     * Setujui pengajuan izin.
     * - Tandai absensi_pegawai sesuai jenis (izin/sakit/dinas) untuk setiap hari dalam rentang.
     */
    public function setujui(PengajuanIzin $pengajuan, User $penyetuju): PengajuanIzin
    {
        if ($pengajuan->status !== 'diajukan') {
            throw new SdmException('Pengajuan izin sudah diproses.');
        }

        return DB::transaction(function () use ($pengajuan, $penyetuju) {
            $pengajuan->update([
                'status' => 'disetujui',
                'disetujui_oleh' => $penyetuju->id,
                'disetujui_pada' => now(),
            ]);

            $this->tandaiAbsensiIzin($pengajuan);

            return $pengajuan;
        });
    }

    /**
     * Tolak pengajuan izin.
     */
    public function tolak(PengajuanIzin $pengajuan, User $penyetuju, ?string $alasan = null): PengajuanIzin
    {
        if ($pengajuan->status !== 'diajukan') {
            throw new SdmException('Pengajuan izin sudah diproses.');
        }

        $pengajuan->update([
            'status' => 'ditolak',
            'disetujui_oleh' => $penyetuju->id,
            'disetujui_pada' => now(),
            'alasan' => $alasan ?? $pengajuan->alasan,
        ]);

        return $pengajuan;
    }

    /**
     * Batalkan pengajuan izin.
     * Jika sudah disetujui, hapus absensi izin yang dibuat.
     */
    public function batalkan(PengajuanIzin $pengajuan): PengajuanIzin
    {
        if ($pengajuan->status === 'dibatalkan') {
            throw new SdmException('Pengajuan izin sudah dibatalkan.');
        }

        return DB::transaction(function () use ($pengajuan) {
            if ($pengajuan->status === 'disetujui') {
                $statusAbsensi = match ($pengajuan->jenis) {
                    'izin' => 'izin',
                    'sakit' => 'sakit',
                    'dinas_luar' => 'dinas',
                    default => 'izin',
                };

                \App\Models\AbsensiPegawai::query()
                    ->where('pegawai_id', $pengajuan->pegawai_id)
                    ->whereBetween('tanggal', [$pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai])
                    ->where('status', $statusAbsensi)
                    ->delete();
            }

            $pengajuan->update(['status' => 'dibatalkan']);

            return $pengajuan;
        });
    }

    /**
     * Tandai absensi_pegawai sesuai jenis izin untuk setiap hari dalam rentang.
     */
    private function tandaiAbsensiIzin(PengajuanIzin $pengajuan): void
    {
        $statusAbsensi = match ($pengajuan->jenis) {
            'izin' => 'izin',
            'sakit' => 'sakit',
            'dinas_luar' => 'dinas',
            default => 'izin',
        };

        $mulai = Carbon::parse($pengajuan->tanggal_mulai);
        $selesai = Carbon::parse($pengajuan->tanggal_selesai);
        $absensiSvc = app(AbsensiService::class);

        $tanggal = $mulai->copy();
        while ($tanggal->lte($selesai)) {
            $tanggalStr = $tanggal->toDateString();
            $penugasan = $absensiSvc->penugasanAktifPada($pengajuan->pegawai, $tanggalStr);

            if ($penugasan !== null) {
                $absensiSvc->catatManual($pengajuan->pegawai, $tanggalStr, [
                    'status' => $statusAbsensi,
                    'keterangan' => ucfirst($pengajuan->jenis).' (#'.$pengajuan->id.')',
                ], $pengajuan->disetujui_oleh);
            }

            $tanggal->addDay();
        }
    }
}
