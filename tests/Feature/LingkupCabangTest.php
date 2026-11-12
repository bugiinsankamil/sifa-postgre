<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Orang;
use App\Models\SpmbPendaftar;
use App\Models\User;
use App\Services\PenugasanPeran;
use App\Services\Spmb\PendaftaranSpmb;
use Database\Seeders\PeranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

/**
 * Memastikan data SPMB hanya terlihat oleh cabang yang berhak, tanpa bergantung pada middleware.
 */
class LingkupCabangTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        $this->seed(PeranSeeder::class);

        $this->a = $this->periodeTerbuka([], 'FTN-01');
        $this->b = $this->periodeTerbuka([], 'FTN-02');

        $layanan = app(PendaftaranSpmb::class);
        $layanan->daftar($this->a['periode'], $this->dataPendaftaran($this->a, ['nama_lengkap' => 'Calon Cabang Satu']));
        $layanan->daftar($this->b['periode'], $this->dataPendaftaran($this->b, ['nama_lengkap' => 'Calon Cabang Dua']));
    }

    private function pengguna(string $username, Peran $peran, ?int $cabangId = null): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'rahasia-uji-123']);
        app(PenugasanPeran::class)->berikan($user, $peran, $cabangId);

        return $user;
    }

    public function test_tanpa_pengguna_login_data_tidak_dibatasi(): void
    {
        $this->assertSame(2, SpmbPendaftar::query()->count());
    }

    public function test_admin_cabang_hanya_melihat_cabangnya(): void
    {
        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']));

        $this->assertSame(['Calon Cabang Satu'], SpmbPendaftar::query()->pluck('nama_lengkap')->all());
    }

    public function test_peran_di_dua_cabang_melihat_keduanya(): void
    {
        $user = $this->pengguna('admin2', Peran::Admin, $this->a['cabang']);
        app(PenugasanPeran::class)->berikan($user, Peran::Admin, $this->b['cabang']);
        $this->actingAs($user);

        $this->assertSame(2, SpmbPendaftar::query()->count());
    }

    public function test_sysadmin_dan_yayasan_melihat_semua_cabang(): void
    {
        $this->actingAs($this->pengguna('sa', Peran::Sysadmin));
        $this->assertSame(2, SpmbPendaftar::query()->count());

        $this->actingAs($this->pengguna('yys', Peran::Yayasan));
        $this->assertSame(2, SpmbPendaftar::query()->count());
    }

    public function test_pengguna_tanpa_peran_tidak_melihat_apa_pun(): void
    {
        $orang = Orang::create(['nama_lengkap' => 'Tanpa Peran', 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => 'kosong', 'password' => 'rahasia-uji-123']);
        $this->actingAs($user);

        $this->assertSame(0, SpmbPendaftar::query()->count());
    }
}
