<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\Pegawai;
use App\Models\PegawaiRiwayatStatus;
use App\Models\Referensi;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah status kepegawaian pegawai sekaligus mencatat riwayatnya.
 * Riwayat status tidak pernah dihapus; setiap perubahan menambah baris baru.
 */
class StatusKepegawaianService
{
    public function ubahStatus(Pegawai $pegawai, string $statusRef, string $tmt, ?string $noSk = null, ?string $keterangan = null): PegawaiRiwayatStatus
    {
        $this->validasiStatusRef($statusRef);

        if ($pegawai->status_kepegawaian_ref === $statusRef) {
            throw new SdmException('Status kepegawaian pegawai sudah '.$statusRef.'.');
        }

        return DB::transaction(function () use ($pegawai, $statusRef, $tmt, $noSk, $keterangan) {
            $terkunci = Pegawai::query()->lockForUpdate()->findOrFail($pegawai->id);

            $riwayat = PegawaiRiwayatStatus::create([
                'pegawai_id' => $terkunci->id,
                'status_kepegawaian_ref' => $statusRef,
                'tmt' => $tmt,
                'no_sk' => $noSk,
                'keterangan' => $keterangan,
            ]);

            $terkunci->update(['status_kepegawaian_ref' => $statusRef]);

            return $riwayat;
        });
    }

    /** Mencatat status awal tanpa mengubah data pegawai (dipakai saat input pegawai baru). */
    public function catatAwal(Pegawai $pegawai, ?string $noSk = null): PegawaiRiwayatStatus
    {
        return PegawaiRiwayatStatus::create([
            'pegawai_id' => $pegawai->id,
            'status_kepegawaian_ref' => $pegawai->status_kepegawaian_ref,
            'tmt' => $pegawai->tanggal_masuk->toDateString(),
            'no_sk' => $noSk,
            'keterangan' => 'Status awal saat pegawai terdaftar',
        ]);
    }

    private function validasiStatusRef(string $statusRef): void
    {
        $ada = Referensi::query()
            ->where('kategori', 'status_kepegawaian')
            ->where('kode', $statusRef)
            ->where('is_aktif', true)
            ->exists();

        if (! $ada) {
            throw new SdmException('Status kepegawaian "'.$statusRef.'" tidak dikenal atau tidak aktif.');
        }
    }
}
