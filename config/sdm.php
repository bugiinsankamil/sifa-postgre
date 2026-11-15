<?php

return [
    /*
     * Peran yang boleh mengelola kepegawaian (SDM) di panel admin.
     * Data per cabang tetap dibatasi lewat CabangKonteks.
     */

    // Melihat data kepegawaian cabangnya
    'peran_lihat' => ['sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'admin'],

    // Mengelola master (jabatan, shift, lokasi/perangkat absensi, pelatihan) dan data pegawai
    'peran_kelola' => ['sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'admin'],

    // Mengubah status kepegawaian (mencatat riwayat status) dan mengakhiri kontrak
    'peran_status' => ['sysadmin', 'yayasan', 'direktur', 'kepala_cabang'],

    // Batas hari sebelum kontrak berakhir untuk peringatan scheduler
    'peringatan_kontrak_hari' => 30,
];
