<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Filament\Resources\Spmb\SpmbPendaftarResource;
use App\Filament\Resources\Spmb\SpmbPeriodeResource;
use App\Models\Orang;
use App\Models\User;
use App\Services\PenugasanPeran;
use App\Services\Spmb\PendaftaranSpmb;
use Database\Seeders\PeranSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

/**
 * Menguji akses ke panel admin SPMB lewat HTTP. Membutuhkan panel Filament terpasang dan User yang
 * mengimplementasikan FilamentUser (sudah ada di app/Models/User.php paket ini).
 */
class PanelSpmbTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        $this->seed(PeranSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

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

    public function test_admin_cabang_melihat_daftar_pendaftar_cabangnya_saja(): void
    {
        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(SpmbPendaftarResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Calon Cabang Satu')
            ->assertDontSee('Calon Cabang Dua');
    }

    public function test_sysadmin_melihat_semua_cabang(): void
    {
        $this->actingAs($this->pengguna('sa', Peran::Sysadmin))
            ->get(SpmbPendaftarResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Calon Cabang Satu')
            ->assertSee('Calon Cabang Dua');
    }

    public function test_halaman_periode_dapat_dibuka_panitia(): void
    {
        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(SpmbPeriodeResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Gelombang 1');
    }

    public function test_guru_tidak_boleh_masuk_panel(): void
    {
        $this->actingAs($this->pengguna('guru1', Peran::Guru, $this->a['cabang']))
            ->get(SpmbPendaftarResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_admin_cabang_tidak_dapat_membuka_pendaftar_cabang_lain(): void
    {
        $idCabangDua = \App\Models\SpmbPendaftar::query()->withoutGlobalScopes()->where('cabang_id', $this->b['cabang'])->value('id');

        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(SpmbPendaftarResource::getUrl('view', ['record' => $idCabangDua]))
            ->assertNotFound();
    }

    public function test_umumkan_hasil_memanggil_service_dan_menampilkan_notifikasi(): void
    {
        $this->seed(\Database\Seeders\TemplatPesanSeeder::class);
        $p = app(PendaftaranSpmb::class)->daftar($this->a['periode'], $this->dataPendaftaran($this->a, [
            'nik' => '1671'.str_pad((string) abs(crc32('PanelSpmbTest')), 12, '0', STR_PAD_LEFT),
        ]));
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->a['cabang']);
        $this->actingAs($user);

        \Illuminate\Support\Facades\DB::table('spmb_hasil')->insert([
            'pendaftar_id' => $p->id, 'keputusan' => 'lulus', 'tanggal_keputusan' => today(),
            'ditetapkan_oleh' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $p->update(['status' => 'lulus']);

        \Livewire\Livewire::test(\App\Filament\Resources\Spmb\Pages\ListSpmbPeriode::class)
            ->callAction(\Filament\Actions\Testing\TestAction::make('umumkanHasil')->table($this->a['periode']));

        $this->assertNotNull($this->a['periode']->fresh()->hasil_diumumkan_pada);
        \Filament\Notifications\Notification::assertNotified('Hasil diumumkan; 1 pesan diantrekan');
    }
}
