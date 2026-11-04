<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Orang;
use App\Models\User;
use App\Services\PenugasanPeran;
use Database\Seeders\PeranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Menguji seeder peran dan konvensi team (0 = yayasan, selain itu = cabang_id).
 * Jalankan terhadap PostgreSQL: php artisan test --filter=PeranTest
 * Bila ada yang gagal, kemungkinan besar perilaku versi spatie/laravel-permission yang terpasang
 * berbeda dari asumsi di PenugasanPeran; periksa dokumentasi bagian Teams.
 */
class PeranTest extends TestCase
{
    use RefreshDatabase;

    private PenugasanPeran $layanan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PeranSeeder::class);
        $this->layanan = new PenugasanPeran();
    }

    private function buatPengguna(string $username = 'tes'): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna Uji', 'jenis_kelamin' => 'L']);

        return User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'rahasia-uji']);
    }

    public function test_semua_peran_terbuat_sebagai_peran_global(): void
    {
        $this->assertSame(count(Peran::cases()), Role::query()->whereNull('cabang_id')->count());
        $this->assertEqualsCanonicalizing(Peran::namaSemua(), Role::query()->pluck('name')->all());
    }

    public function test_seeder_dapat_dijalankan_ulang_tanpa_duplikat(): void
    {
        $this->seed(PeranSeeder::class);

        $this->assertSame(count(Peran::cases()), Role::query()->count());
    }

    public function test_peran_yayasan_tersimpan_pada_team_0(): void
    {
        $user = $this->buatPengguna();
        $this->layanan->berikan($user, Peran::Yayasan);

        $this->assertSame(0, (int) DB::table('model_has_roles')->where('model_id', $user->id)->value('cabang_id'));
        $this->assertTrue($this->layanan->punya($user, Peran::Yayasan));
    }

    public function test_peran_cabang_tersimpan_pada_cabang_yang_diberikan(): void
    {
        $user = $this->buatPengguna();
        $this->layanan->berikan($user, Peran::KepalaCabang, 5);

        $this->assertSame(5, (int) DB::table('model_has_roles')->where('model_id', $user->id)->value('cabang_id'));
        $this->assertTrue($this->layanan->punya($user, Peran::KepalaCabang, 5));
        $this->assertFalse($this->layanan->punya($user, Peran::KepalaCabang, 6));
    }

    public function test_peran_yang_sama_dapat_dipegang_di_beberapa_cabang(): void
    {
        $user = $this->buatPengguna();
        $this->layanan->berikan($user, Peran::Guru, 1);
        $this->layanan->berikan($user, Peran::Guru, 3);

        $this->assertSame(2, DB::table('model_has_roles')->where('model_id', $user->id)->count());
        $this->assertTrue($this->layanan->punya($user, Peran::Guru, 1));
        $this->assertTrue($this->layanan->punya($user, Peran::Guru, 3));
        $this->assertFalse($this->layanan->punya($user, Peran::Guru, 2));
    }

    public function test_peran_cabang_tanpa_cabang_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->layanan->berikan($this->buatPengguna(), Peran::Guru);
    }

    public function test_cabut_peran_hanya_pada_cabang_yang_ditentukan(): void
    {
        $user = $this->buatPengguna();
        $this->layanan->berikan($user, Peran::Guru, 1);
        $this->layanan->berikan($user, Peran::Guru, 3);

        $this->layanan->cabut($user, Peran::Guru, 1);

        $this->assertFalse($this->layanan->punya($user, Peran::Guru, 1));
        $this->assertTrue($this->layanan->punya($user, Peran::Guru, 3));
    }
}
