<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            JenjangSeeder::class,
            TingkatSeeder::class,
            ReferensiSeeder::class,
            TemplatPesanSeeder::class,
            PeranSeeder::class,         // butuh tabel spatie/laravel-permission sudah dimigrasi
            AkunSysadminSeeder::class,  // dilewati bila SYSADMIN_* di .env belum diisi
        ]);
    }
}
