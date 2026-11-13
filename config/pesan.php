<?php

use App\Pesan\EmailPengirim;
use App\Pesan\LogPengirim;

return [
    // Saklar utama. false: tidak ada pesan yang diantrekan maupun dikirim.
    'aktif' => env('PESAN_AKTIF', true),

    // Pengirim yang dipakai tiap kanal (nama kunci pada 'driver' di bawah).
    // 'log' hanya menulis ke log aplikasi, tidak mengirim apa pun. Ganti ke penyedia nyata bila sudah dipilih.
    'pengirim' => [
        'whatsapp' => env('PESAN_PENGIRIM_WHATSAPP', 'log'),
        'email' => env('PESAN_PENGIRIM_EMAIL', 'surel'),
    ],

    // Daftar pengirim yang tersedia. Menambah penyedia baru = menambah kelas yang mengimplementasikan
    // App\Pesan\Pengirim dan mendaftarkannya di sini.
    'driver' => [
        'log' => LogPengirim::class,
        'surel' => EmailPengirim::class,
    ],

    // Pengiriman ulang bila gagal
    'maks_percobaan' => 5,
    'backoff_menit' => [1, 5, 15, 60, 180],

    // Pembatasan laju (penting untuk WhatsApp agar nomor tidak diblokir)
    'batas_per_proses' => 20,   // jumlah pesan per eksekusi perintah (dijalankan tiap menit)
    'jeda_ms' => 1500,          // jeda antar pesan

    // Pesan tidak dikirim pada jam ini (diundur ke pagi). Gunakan zona waktu aplikasi (APP_TIMEZONE).
    // Isi null untuk mematikan.
    'jam_tenang' => ['mulai' => '21:00', 'selesai' => '06:00'],

    // Pesan berstatus diproses lebih lama dari ini dianggap macet dan diantrekan kembali
    'proses_macet_menit' => 10,

    // Peran yang boleh melihat antrean pesan, dan yang boleh mengubah templat
    'peran_lihat' => ['sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'kepala_sekolah', 'admin'],
    'peran_templat' => ['sysadmin', 'yayasan', 'direktur'],
];
