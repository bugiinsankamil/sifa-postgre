<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TingkatSeeder extends Seeder
{
    public function run(): void
    {
        $jenjang = DB::table('jenjang')->pluck('id', 'kode');
        $sekarang = now();

        $daftar = [
            'TK' => [['A', 'TK A'], ['B', 'TK B']],
            'SD' => array_map(fn ($n) => [(string) $n, "Kelas $n"], range(1, 6)),
            'SMP' => array_map(fn ($n) => [(string) $n, "Kelas $n"], range(7, 9)),
        ];

        $baris = [];
        foreach ($daftar as $kodeJenjang => $tingkat) {
            foreach ($tingkat as $i => [$kode, $nama]) {
                $baris[] = [
                    'jenjang_id' => $jenjang[$kodeJenjang],
                    'kode' => $kode,
                    'nama' => $nama,
                    'urutan' => $i + 1,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ];
            }
        }

        DB::table('tingkat')->upsert($baris, ['jenjang_id', 'kode'], ['nama', 'urutan', 'updated_at']);
    }
}
