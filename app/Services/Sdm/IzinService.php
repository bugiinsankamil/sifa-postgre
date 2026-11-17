<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiPegawai;
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
        if ($tanggalSelesai < $tanggalMulai) {
            throw new SdmException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        $penugasan = app(AbsensiService::class)->penugasanAktifPada($pegawai, $tanggalMulai);
        if ($penugasan === null) {
            throw new SdmException('Pegawai tidak memiliki penugasan aktif pada tanggal '.$tanggalMulai.'.');
        }

        // Cek pengajuan izin bertumpang tindih
        $tumpangTindih = PengajuanIzin::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereIn('status', ['diajukan', 'disetujui'])
            ->where('tanggal_mulai', '<=', $tanggalSelesai)
            ->where('tanggal_selesai', '>=', $tanggalMulai)
            ->exists();
        if ($tumpangTindih) {
            throw new SdmException('Sudah ada pengajuan izin pada rentang tanggal tersebut.');
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
     */
    public function setujui(PengajuanIzin $pengajuan, User $penyetuju): PengajuanIzin
    {
        return DB::transaction(function () use ($pengajuan, $penyetuju) {
            $terkunci = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($terkunci->status !== 'diajukan') {
                throw new SdmException('Pengajuan izin sudah diproses.');
            }

            $terkunci->update([
                'status' => 'disetujui',
                'disetujui_oleh' => $penyetuju->id,
                'disetujui_pada' => now(),
            ]);

            $this->tandaiAbsensiIzin($terkunci);

            return $terkunci;
        });
    }

    /**
     * Tolak pengajuan izin.
     */
    public function tolak(PengajuanIzin $pengajuan, User $penyetuju, ?string $alasanPenolakan = null): PengajuanIzin
    {
        return DB::transaction(function () use ($pengajuan, $penyetuju, $alasanPenolakan) {
            $terkunci = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($terkunci->status !== 'diajukan') {
                throw new SdmException('Pengajuan izin sudah diproses.');
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
     * Batalkan pengajuan izin.
     * Jika sudah disetujui, hapus absensi milik pengajuan ini.
     */
    public function batalkan(PengajuanIzin $pengajuan): PengajuanIzin
    {
        return DB::transaction(function () use ($pengajuan) {
            $terkunci = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($terkunci->status === 'dibatalkan') {
                throw new SdmException('Pengajuan izin sudah dibatalkan.');
            }

            if ($terkunci->status === 'disetujui') {
                AbsensiPegawai::query()
                    ->where('pengajuan_izin_id', $terkunci->id)
                    ->delete();
            }

            $terkunci->update(['status' => 'dibatalkan']);

            return $terkunci;
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
                    'pengajuan_izin_id' => $pengajuan->id,
                ], $pengajuan->disetujui_oleh);
            }

            $tanggal->addDay();
        }
    }
}
