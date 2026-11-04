<?php

namespace Database\Seeders;

use App\Enums\Peran;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuat definisi peran. Peran dibuat sebagai peran global (kolom cabang_id pada tabel roles = NULL)
 * sehingga bisa diberikan pada team mana pun. Cabang yang memegang peran ditentukan saat peran
 * diberikan ke pengguna (lihat App\Services\PenugasanPeran), bukan di sini.
 *
 * Seeder ini hanya membuat peran. Izin (permission) ditambahkan per modul setelah modulnya ada.
 */
class PeranSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('permission.teams')) {
            throw new RuntimeException("Set 'teams' => true dan 'team_foreign_key' => 'cabang_id' di config/permission.php sebelum menjalankan PeranSeeder.");
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $kelasPeran = $registrar->getRoleClass();
        $kunciTeam = config('permission.column_names.team_foreign_key', 'team_id');

        foreach (Peran::cases() as $peran) {
            // Query builder, bukan Role::create(), agar kolom team tidak otomatis terisi team yang sedang aktif
            $kelasPeran::query()->firstOrCreate([
                'name' => $peran->value,
                'guard_name' => 'web',
                $kunciTeam => null,
            ]);
        }

        $registrar->forgetCachedPermissions();
    }
}
