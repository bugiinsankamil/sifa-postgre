<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenjangSeeder extends Seeder
{
    public function run(): void
    {
        $sekarang = now();

        DB::table('jenjang')->upsert([
            ['kode' => 'TK',  'nama' => 'Taman Kanak-kanak',        'urutan' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang],
            ['kode' => 'SD',  'nama' => 'Sekolah Dasar',            'urutan' => 2, 'created_at' => $sekarang, 'updated_at' => $sekarang],
            ['kode' => 'SMP', 'nama' => 'Sekolah Menengah Pertama', 'urutan' => 3, 'created_at' => $sekarang, 'updated_at' => $sekarang],
        ], ['kode'], ['nama', 'urutan', 'updated_at']);
    }
}
