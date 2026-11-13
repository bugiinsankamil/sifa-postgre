<?php

namespace App\Pesan;

use Illuminate\Support\Facades\Log;

/** Tidak mengirim apa pun; hanya mencatat ke log. Aman untuk pengembangan dan masa sebelum penyedia dipilih. */
class LogPengirim implements Pengirim
{
    public function kirim(string $tujuan, string $isi, ?string $judul = null): HasilKirim
    {
        Log::info("[pesan] ke {$tujuan}", ['judul' => $judul, 'isi' => $isi]);

        return HasilKirim::sukses('log-'.uniqid());
    }
}
