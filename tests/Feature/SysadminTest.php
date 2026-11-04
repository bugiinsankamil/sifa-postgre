<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Orang;
use App\Models\User;
use App\Services\PenugasanPeran;
use Database\Seeders\AkunSysadminSeeder;
use Database\Seeders\PeranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\TestCase;

/**
 * Prasyarat: App\Providers\AksesServiceProvider terdaftar di bootstrap/providers.php.
 * Jalankan: php artisan test --filter=SysadminTest
 */
class SysadminTest extends TestCase
{
    use RefreshDatabase;

    private PenugasanPeran $layanan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PeranSeeder::class);
        $this->layanan = new PenugasanPeran();
    }

    private function buatPengguna(string $username): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);

        return User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'rahasia-uji-123']);
    }

    public function test_sysadmin_lolos_semua_pemeriksaan_gerbang(): void
    {
        $sysadmin = $this->buatPengguna('sa');
        $biasa = $this->buatPengguna('biasa');
        $this->layanan->berikan($sysadmin, Peran::Sysadmin);

        $this->assertTrue($sysadmin->isSysadmin());
        $this->assertTrue(Gate::forUser($sysadmin)->allows('izin-apa-saja'));
        $this->assertFalse($biasa->isSysadmin());
        $this->assertFalse(Gate::forUser($biasa)->allows('izin-apa-saja'));
    }

    public function test_peran_sysadmin_tersimpan_pada_team_0(): void
    {
        $user = $this->buatPengguna('sa');
        $this->layanan->berikan($user, Peran::Sysadmin);

        $this->assertSame(0, (int) DB::table('model_has_roles')->where('model_id', $user->id)->value('cabang_id'));
    }

    public function test_sysadmin_terakhir_tidak_bisa_dicabut(): void
    {
        $user = $this->buatPengguna('sa');
        $this->layanan->berikan($user, Peran::Sysadmin);

        $this->expectException(RuntimeException::class);
        $this->layanan->cabut($user, Peran::Sysadmin);
    }

    public function test_sysadmin_dapat_dicabut_bila_masih_ada_sysadmin_lain(): void
    {
        $a = $this->buatPengguna('sa1');
        $b = $this->buatPengguna('sa2');
        $this->layanan->berikan($a, Peran::Sysadmin);
        $this->layanan->berikan($b, Peran::Sysadmin);

        $this->layanan->cabut($a, Peran::Sysadmin);

        $this->assertFalse($a->isSysadmin());
        $this->assertTrue($b->isSysadmin());
    }

    public function test_seeder_akun_awal_membuat_sysadmin_dan_aman_diulang(): void
    {
        config(['sysadmin' => [
            'username' => 'admin.utama',
            'password' => 'kata-sandi-uji-panjang',
            'nama' => 'Administrator Utama',
            'jenis_kelamin' => 'L',
            'email' => null,
        ]]);

        $this->seed(AkunSysadminSeeder::class);
        $this->seed(AkunSysadminSeeder::class);

        $this->assertSame(1, User::query()->where('username', 'admin.utama')->count());
        $this->assertTrue(User::query()->where('username', 'admin.utama')->firstOrFail()->isSysadmin());
    }

    public function test_seeder_akun_awal_dilewati_bila_konfigurasi_kosong(): void
    {
        config(['sysadmin' => []]);

        $this->seed(AkunSysadminSeeder::class);

        $this->assertSame(0, User::query()->count());
    }
}
