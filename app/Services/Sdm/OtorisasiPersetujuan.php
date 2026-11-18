<?php

namespace App\Services\Sdm;

use App\Enums\Peran;
use App\Exceptions\Sdm\SdmException;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksaan hak penyetuju pada eksekusi aksi (bukan sekadar visibilitas tombol).
 *
 * Aturan: penyetuju memegang salah satu peran config('sdm.peran_status') pada cabang pengajuan
 * (atau team 0 = yayasan). Menyetujui/menolak pengajuan sendiri hanya boleh bagi kepala cabang
 * pada cabang pengajuan tersebut.
 */
class OtorisasiPersetujuan
{
    public function pastikan(User $penyetuju, int $cabangId, Pegawai $pemohon): void
    {
        $peran = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $penyetuju->getMorphClass())
            ->where('model_has_roles.model_id', $penyetuju->getKey())
            ->get(['roles.name as nama', 'model_has_roles.cabang_id']);

        $peranBerhak = config('sdm.peran_status', []);

        $berhak = $peran->contains(
            fn ($p) => in_array($p->nama, $peranBerhak, true)
                && ((int) $p->cabang_id === 0 || (int) $p->cabang_id === $cabangId)
        );

        if (! $berhak) {
            throw new SdmException('Anda tidak berhak memproses pengajuan ini.');
        }

        if ($pemohon->orang_id !== null && $pemohon->orang_id === $penyetuju->orang_id) {
            $kepalaCabang = $peran->contains(
                fn ($p) => $p->nama === Peran::KepalaCabang->value && (int) $p->cabang_id === $cabangId
            );

            if (! $kepalaCabang) {
                throw new SdmException('Pengajuan sendiri hanya dapat diproses oleh kepala cabang.');
            }
        }
    }
}
