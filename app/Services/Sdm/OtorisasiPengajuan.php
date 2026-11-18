<?php

namespace App\Services\Sdm;

use App\Enums\Peran;
use App\Exceptions\Sdm\SdmException;
use App\Models\Pegawai;
use App\Models\PengajuanCuti;
use App\Models\PengajuanIzin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksaan hak pada eksekusi aksi cuti/izin (bukan hanya visibilitas tombol).
 *
 * Aturan bisnis yang disepakati:
 * - Pengajuan sendiri dilarang untuk semua peran.
 * - Kepala cabang boleh memproses pengajuan sendiri di cabangnya sebagai pengecualian.
 * - Persetujuan bertingkat:
 *   1. Atasan langsung = kepala unit_kerja dari penugasan utama pegawai saat pengajuan.
 *   2. Persetujuan final = peran config('sdm.peran_status') atau kepala cabang pada cabang pengajuan.
 * - Pembatalan:
 *   - status diajukan: pemohon sendiri.
 *   - status disetujui/disetujui_atasan: kepala cabang dengan alasan wajib.
 */
class OtorisasiPengajuan
{
    /** Pastikan user boleh mengajukan cuti/izin untuk pegawai ini. */
    public function pastikanBolehMengajukan(User $pengaju, Pegawai $pegawai, int $cabangId): void
    {
        $this->pastikanBukanPengajuanSendiri($pengaju, $pegawai, $cabangId, true);
    }

    /** Pastikan user boleh menyetujui sebagai atasan langsung. */
    public function pastikanAtasan(PengajuanCuti|PengajuanIzin $pengajuan, User $penyetuju): void
    {
        $this->pastikanBukanPengajuanSendiri($penyetuju, $pengajuan->pegawai, $pengajuan->cabang_id, false);
        $atasan = $this->atasanLangsung($pengajuan->pegawai, $pengajuan->tanggal_mulai->toDateString());

        if ($atasan === null || $atasan->orang_id === null || $atasan->orang_id !== $penyetuju->orang_id) {
            throw new SdmException('Anda tidak berhak menyetujui sebagai atasan langsung.');
        }
    }

    /** Pastikan user boleh memberi persetujuan final. */
    public function pastikanPersetujuanFinal(PengajuanCuti|PengajuanIzin $pengajuan, User $penyetuju): void
    {
        $this->pastikanBukanPengajuanSendiri($penyetuju, $pengajuan->pegawai, $pengajuan->cabang_id, false);
        if (! $this->berhakPersetujuanFinal($penyetuju, $pengajuan->cabang_id)) {
            throw new SdmException('Anda tidak berhak memberi persetujuan akhir.');
        }
    }

    /** Pastikan user boleh menolak pengajuan. */
    public function pastikanPenolakan(PengajuanCuti|PengajuanIzin $pengajuan, User $penyetuju): void
    {
        $this->pastikanBukanPengajuanSendiri($penyetuju, $pengajuan->pegawai, $pengajuan->cabang_id, false);
        if (! $this->berhakPersetujuanFinal($penyetuju, $pengajuan->cabang_id)) {
            throw new SdmException('Anda tidak berhak menolak pengajuan ini.');
        }
    }

    /** Pastikan user boleh membatalkan pengajuan sesuai status. */
    public function pastikanPembatalan(PengajuanCuti|PengajuanIzin $pengajuan, User $pembatal): void
    {
        if (in_array($pengajuan->status, ['ditolak', 'dibatalkan'], true)) {
            throw new SdmException('Pengajuan sudah berstatus terminal.');
        }

        $statusDiajukan = $pengajuan->status === 'diajukan';

        // Pemohon sendiri boleh membatalkan saat masih diajukan
        if ($statusDiajukan && $pengajuan->pegawai->orang_id !== null && $pengajuan->pegawai->orang_id === $pembatal->orang_id) {
            return;
        }

        // Kepala cabang boleh membatalkan diajukan maupun yang sudah disetujui/atasan
        if ($this->adalahKepalaCabang($pembatal, $pengajuan->cabang_id)) {
            return;
        }

        if ($statusDiajukan) {
            throw new SdmException('Pembatalan pengajuan sendiri hanya boleh dilakukan oleh pemohon atau kepala cabang.');
        }

        throw new SdmException('Pembatalan status ini hanya boleh dilakukan oleh kepala cabang.');
    }

    /** Tentukan atasan langsung pegawai pada tanggal tertentu. */
    public function atasanLangsung(Pegawai $pegawai, string $tanggal): ?Pegawai
    {
        $penugasan = app(AbsensiService::class)->penugasanAktifPada($pegawai, $tanggal, false);
        if ($penugasan === null || $penugasan->unit_kerja_id === null) {
            return null;
        }

        return $penugasan->unitKerja?->kepala;
    }

    private function pastikanBukanPengajuanSendiri(User $user, Pegawai $pemohon, int $cabangId, bool $pengajuanBaru): void
    {
        if ($pemohon->orang_id !== null && $pemohon->orang_id === $user->orang_id) {
            if ($this->adalahKepalaCabang($user, $cabangId)) {
                return;
            }

            throw new SdmException('Pengajuan cuti/izin sendiri hanya dapat diproses oleh kepala cabang.');
        }
    }

    private function berhakPersetujuanFinal(User $user, int $cabangId): bool
    {
        $peran = $this->peranUser($user);
        $peranBerhak = config('sdm.peran_status', []);

        return $peran->contains(
            fn ($p) => in_array($p->nama, $peranBerhak, true)
                && ((int) $p->cabang_id === 0 || (int) $p->cabang_id === $cabangId)
        );
    }

    private function adalahKepalaCabang(User $user, int $cabangId): bool
    {
        return $this->peranUser($user)->contains(
            fn ($p) => $p->nama === Peran::KepalaCabang->value
                && ((int) $p->cabang_id === 0 || (int) $p->cabang_id === $cabangId)
        );
    }

    private function peranUser(User $user)
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->getKey())
            ->get(['roles.name as nama', 'model_has_roles.cabang_id']);
    }
}
