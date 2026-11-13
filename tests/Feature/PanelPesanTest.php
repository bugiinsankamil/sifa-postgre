<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Filament\Resources\Pesan\AntreanPesanResource;
use App\Filament\Resources\Pesan\TemplatPesanResource;
use App\Models\AntreanPesan;
use App\Models\Orang;
use App\Models\User;
use App\Services\PenugasanPeran;
use Database\Seeders\PeranSeeder;
use Database\Seeders\TemplatPesanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class PanelPesanTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        $this->seed(PeranSeeder::class);
        $this->seed(TemplatPesanSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        $this->a = $this->periodeTerbuka([], 'FTN-01');
        $this->b = $this->periodeTerbuka([], 'FTN-02');

        foreach ([[$this->a, 'satu'], [$this->b, 'dua']] as [$s, $nama]) {
            AntreanPesan::query()->create([
                'cabang_id' => $s['cabang'], 'kanal' => 'whatsapp', 'tujuan' => '6281234567890',
                'isi' => "Pesan khusus cabang {$nama}", 'status' => 'antri',
            ]);
        }
    }

    private function pengguna(string $username, Peran $peran, ?int $cabangId = null): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'rahasia-uji-123']);
        app(PenugasanPeran::class)->berikan($user, $peran, $cabangId);

        return $user;
    }

    public function test_admin_cabang_hanya_melihat_antrean_cabangnya(): void
    {
        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(AntreanPesanResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Pesan khusus cabang satu')
            ->assertDontSee('Pesan khusus cabang dua');
    }

    public function test_guru_tidak_boleh_melihat_antrean(): void
    {
        $this->actingAs($this->pengguna('guru1', Peran::Guru, $this->a['cabang']))
            ->get(AntreanPesanResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_templat_hanya_untuk_pengelola(): void
    {
        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(TemplatPesanResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($this->pengguna('sa', Peran::Sysadmin))
            ->get(TemplatPesanResource::getUrl('index'))
            ->assertOk()
            ->assertSee('spmb_terdaftar');
    }
}
