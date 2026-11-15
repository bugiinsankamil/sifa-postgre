<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Pegawai;

/**
 * Pendaftaran peserta pelatihan dan pencatatan hasilnya.
 */
class PelatihanService
{
    public function daftarkan(Pelatihan $pelatihan, Pegawai $pegawai): PelatihanPeserta
    {
        if (in_array($pelatihan->status, ['selesai', 'dibatalkan'], true)) {
            throw new SdmException('Pelatihan sudah selesai atau dibatalkan; pendaftaran ditutup.');
        }

        $sudah = PelatihanPeserta::query()
            ->where('pelatihan_id', $pelatihan->id)
            ->where('pegawai_id', $pegawai->id)
            ->exists();

        if ($sudah) {
            throw new SdmException('Pegawai sudah terdaftar pada pelatihan ini.');
        }

        return PelatihanPeserta::create([
            'pelatihan_id' => $pelatihan->id,
            'pegawai_id' => $pegawai->id,
            'status' => 'terdaftar',
        ]);
    }

    public function catatHasil(PelatihanPeserta $peserta, string $status, ?float $nilai = null, ?string $nomorSertifikat = null): PelatihanPeserta
    {
        if (! in_array($status, ['hadir', 'lulus', 'tidak_hadir'], true)) {
            throw new SdmException('Status hasil pelatihan tidak valid.');
        }

        $peserta->update([
            'status' => $status,
            'nilai' => $nilai,
            'nomor_sertifikat' => $nomorSertifikat,
        ]);

        return $peserta;
    }
}
