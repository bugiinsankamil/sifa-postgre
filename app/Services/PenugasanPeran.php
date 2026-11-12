<?php

namespace App\Services;

use App\Enums\Peran;
use App\Support\CabangKonteks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Memberi, mencabut, dan memeriksa peran pengguna dengan team yang benar.
 *
 * Konvensi: peran tingkat yayasan (sysadmin, yayasan, direktur) disimpan pada team 0; peran lain pada
 * team = cabang_id. Satu pengguna boleh memegang peran berbeda di cabang berbeda, atau peran yang
 * sama di beberapa cabang.
 */
class PenugasanPeran
{
    public function berikan(Model $pengguna, Peran $peran, ?int $cabangId = null): void
    {
        $this->denganTeam($pengguna, $peran, $cabangId, fn () => $pengguna->assignRole($peran->value));
        $this->segarkan($pengguna);
    }

    public function cabut(Model $pengguna, Peran $peran, ?int $cabangId = null): void
    {
        // Aplikasi tidak boleh kehilangan administrator utamanya
        if ($peran === Peran::Sysadmin && $this->punya($pengguna, $peran) && $this->jumlahSysadmin() <= 1) {
            throw new RuntimeException('Peran sysadmin terakhir tidak boleh dicabut. Tunjuk sysadmin lain lebih dulu.');
        }

        $this->denganTeam($pengguna, $peran, $cabangId, fn () => $pengguna->removeRole($peran->value));
        $this->segarkan($pengguna);
    }

    public function punya(Model $pengguna, Peran $peran, ?int $cabangId = null): bool
    {
        $hasil = false;
        $this->denganTeam($pengguna, $peran, $cabangId, function () use ($pengguna, $peran, &$hasil) {
            $hasil = $pengguna->hasRole($peran->value);
        });

        return $hasil;
    }

    private function jumlahSysadmin(): int
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', Peran::Sysadmin->value)
            ->count();
    }

    private function segarkan(Model $pengguna): void
    {
        app(CabangKonteks::class)->lupakan();

        if (method_exists($pengguna, 'segarkanPeran')) {
            $pengguna->segarkanPeran();
        }
    }

    private function denganTeam(Model $pengguna, Peran $peran, ?int $cabangId, callable $aksi): void
    {
        if ($peran->berlakuGlobal()) {
            $team = 0;
        } else {
            if ($cabangId === null || $cabangId <= 0) {
                throw new InvalidArgumentException("Peran {$peran->value} harus diberikan pada satu cabang (cabangId wajib diisi).");
            }
            $team = $cabangId;
        }

        $registrar = app(PermissionRegistrar::class);
        $sebelumnya = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($team);
        $pengguna->unsetRelation('roles');   // relasi roles di-cache per team
        try {
            $aksi();
        } finally {
            $registrar->setPermissionsTeamId($sebelumnya);
            $pengguna->unsetRelation('roles');
        }
    }
}
