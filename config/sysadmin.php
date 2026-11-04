<?php

/**
 * Data akun sysadmin pertama, dibaca oleh Database\Seeders\AkunSysadminSeeder.
 * Isi lewat .env sebelum menjalankan seeder; hapus nilai SYSADMIN_PASSWORD dari .env setelah akun dibuat.
 */
return [
    'username' => env('SYSADMIN_USERNAME'),
    'password' => env('SYSADMIN_PASSWORD'),
    'nama' => env('SYSADMIN_NAMA'),
    'jenis_kelamin' => env('SYSADMIN_JENIS_KELAMIN'),   // L atau P
    'email' => env('SYSADMIN_EMAIL'),
];
