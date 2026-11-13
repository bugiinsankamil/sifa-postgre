<?php

namespace App\Pesan;

/**
 * Kontrak pengirim pesan. Setiap penyedia (WhatsApp resmi, gateway pihak ketiga, email, SMS) cukup
 * mengimplementasikan satu method ini dan didaftarkan di config/pesan.php.
 */
interface Pengirim
{
    /** Tidak perlu menangkap semua galat: pengecualian yang keluar dianggap gagal dan bisa diulang. */
    public function kirim(string $tujuan, string $isi, ?string $judul = null): HasilKirim;
}
