<?php

namespace App\Pesan;

use Illuminate\Support\Facades\Mail;

/** Mengirim teks biasa lewat konfigurasi email Laravel (config/mail.php). */
class EmailPengirim implements Pengirim
{
    public function kirim(string $tujuan, string $isi, ?string $judul = null): HasilKirim
    {
        if (! filter_var($tujuan, FILTER_VALIDATE_EMAIL)) {
            return HasilKirim::gagal("Alamat email tidak valid: {$tujuan}", bisaDiulang: false);
        }

        Mail::raw($isi, function ($pesan) use ($tujuan, $judul) {
            $pesan->to($tujuan)->subject($judul ?: 'Pemberitahuan dari sekolah');
        });

        return HasilKirim::sukses();
    }
}
