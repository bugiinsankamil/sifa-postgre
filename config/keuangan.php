<?php

return [
    /*
     * Peran yang boleh mengelola keuangan di panel admin.
     * Data tetap dibatasi ke cabang/entitas yang berhak lewat konteks pengguna.
     */

    // Melihat semua data keuangan cabangnya (jurnal, neraca saldo)
    'peran_lihat' => ['sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'admin'],

    // Membuat dan mengedit jurnal draft
    'peran_draft' => ['sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'admin'],

    // Memposting jurnal (mengunci jurnal agar tidak bisa diubah)
    'peran_posting' => ['sysadmin', 'yayasan', 'direktur', 'kepala_cabang'],

    // Mengelola master akun, entitas, pusat biaya, pemetaan akun
    'peran_master' => ['sysadmin', 'yayasan', 'direktur'],

    // Menutup periode akuntansi
    'peran_tutup_periode' => ['sysadmin', 'yayasan', 'direktur'],
];
