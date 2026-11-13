<?php

namespace App\Listeners\Spmb;

use App\Events\Spmb\BerkasDitolak;
use App\Events\Spmb\PendaftarTerdaftar;
use App\Events\Spmb\PendaftarTerverifikasi;
use App\Services\Spmb\NotifikasiSpmb;

/**
 * Laravel 11+ menemukan listener ini otomatis lewat method yang berawalan "handle" dan bertipe peristiwa.
 * Listener hanya memasukkan pesan ke antrean (cepat); pengiriman dilakukan perintah pesan:kirim.
 */
class AntrekanNotifikasiSpmb
{
    public function __construct(private readonly NotifikasiSpmb $notifikasi)
    {
    }

    public function handlePendaftarTerdaftar(PendaftarTerdaftar $peristiwa): void
    {
        $this->notifikasi->terdaftar($peristiwa->pendaftar);
    }

    public function handlePendaftarTerverifikasi(PendaftarTerverifikasi $peristiwa): void
    {
        $this->notifikasi->terverifikasi($peristiwa->pendaftar);
    }

    public function handleBerkasDitolak(BerkasDitolak $peristiwa): void
    {
        $this->notifikasi->berkasDitolak($peristiwa->berkas);
    }
}
