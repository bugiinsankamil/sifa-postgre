<?php

namespace App\Providers;

use App\Models\Orang;
use App\Models\User;
use App\Support\CabangKonteks;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Daftarkan di bootstrap/providers.php:  App\Providers\AksesServiceProvider::class
 */
class AksesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CabangKonteks::class);
    }

    public function boot(): void
    {
        // Alias morph yang stabil untuk kolom *_type (subjek_type, lampiran_type, model_type spatie, dst).
        // Tambahkan model baru di sini tiap modul dibuat. Ganti morphMap() menjadi enforceMorphMap()
        // hanya setelah semua model yang dipakai relasi polimorfik terdaftar.
        Relation::morphMap([
            'user' => User::class,
            'orang' => Orang::class,
        ]);

        // Sysadmin lolos semua pemeriksaan gerbang/izin. Pemeriksaan lain (mis. alur persetujuan
        // yang memakai peran atau jabatan secara langsung) tidak otomatis ikut terlewati.
        Gate::before(function ($pengguna, $kemampuan) {
            return $pengguna instanceof User && $pengguna->isSysadmin() ? true : null;
        });
    }
}
