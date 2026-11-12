<?php

return [
    // Ukuran maksimum tiap berkas persyaratan (KB)
    'berkas_maks_kb' => 3072,

    // false: halaman cek status tidak menampilkan hasil seleksi (lulus/cadangan/tidak lulus)
    // sampai Anda mengubahnya menjadi true pada hari pengumuman.
    'tampilkan_hasil_di_status' => false,

    // Nilai referensi spmb_sumber untuk kontak yang dibuat otomatis dari formulir
    'sumber_bawaan' => 'website',

    // Peran yang boleh mengelola SPMB di panel admin (data tetap dibatasi ke cabang masing-masing)
    'peran_panitia' => ['sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'kepala_sekolah', 'admin'],
];
