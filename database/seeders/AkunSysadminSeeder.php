<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Orang;
use App\Models\User;
use App\Services\PenugasanPeran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Membuat akun sysadmin pertama dari config/sysadmin.php (nilai dari .env).
 * Dilewati bila data belum diisi atau username sudah ada, sehingga aman dijalankan berulang.
 * Tidak ada kata sandi bawaan: kata sandi wajib diisi dan minimal 12 karakter.
 */
class AkunSysadminSeeder extends Seeder
{
    public function run(): void
    {
        $c = config('sysadmin', []);

        foreach (['username', 'password', 'nama', 'jenis_kelamin'] as $kunci) {
            if (blank($c[$kunci] ?? null)) {
                $this->command?->warn("Akun sysadmin dilewati: SYSADMIN_" . strtoupper($kunci) . ' belum diisi.');

                return;
            }
        }

        if (! in_array($c['jenis_kelamin'], ['L', 'P'], true)) {
            throw new InvalidArgumentException('SYSADMIN_JENIS_KELAMIN harus L atau P.');
        }
        if (strlen((string) $c['password']) < 8) {
            throw new InvalidArgumentException('SYSADMIN_PASSWORD minimal 8 karakter.');
        }

        if (User::query()->where('username', $c['username'])->exists()) {
            $this->command?->info('Akun sysadmin sudah ada, dilewati.');

            return;
        }

        DB::transaction(function () use ($c) {
            $orang = Orang::create([
                'nama_lengkap' => $c['nama'],
                'jenis_kelamin' => $c['jenis_kelamin'],
                'email' => $c['email'] ?: null,
            ]);

            $user = User::create([
                'orang_id' => $orang->id,
                'username' => $c['username'],
                'email' => $c['email'] ?: null,
                'password' => $c['password'],
                'is_aktif' => true,
            ]);

            app(PenugasanPeran::class)->berikan($user, Peran::Sysadmin);
        });

        $this->command?->info('Akun sysadmin dibuat. Hapus SYSADMIN_PASSWORD dari .env dan ganti kata sandi setelah masuk pertama kali.');
    }
}
