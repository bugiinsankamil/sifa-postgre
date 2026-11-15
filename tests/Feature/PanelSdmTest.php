<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Cabang;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\PenugasanPeran;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelSdmTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferensiSeeder::class);
        $this->seed(PeranSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());
        $this->cabang = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);
    }

    private function pengguna(string $u, Peran $p, ?int $cabangId = null): User
    {
        $orang = Orang::create(['nama_lengkap' => 'User '.$u, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $u, 'password' => 'pw']);
        app(PenugasanPeran::class)->berikan($user, $p, $cabangId);
        return $user;
    }

    private function pegawai(): Pegawai
    {
        $orang = Orang::create(['nama_lengkap' => 'Budi Santoso', 'jenis_kelamin' => 'L']);
        return Pegawai::create([
            'orang_id' => $orang->id,
            'nip_yayasan' => 'NIP-BS',
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
            'status' => 'aktif',
        ]);
    }

    public function test_daftar_pegawai_dapat_diakses_oleh_kepala_cabang(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $pegawai = $this->pegawai();
        $response = $this->get(\App\Filament\Resources\Sdm\PegawaiResource::getUrl('index'));
        $response->assertStatus(200);
    }

    public function test_daftar_pegawai_ditolak_untuk_orang_tua(): void
    {
        $user = $this->pengguna('ortu1', Peran::OrangTua, $this->cabang->id);
        $this->actingAs($user);
        $response = $this->get(\App\Filament\Resources\Sdm\PegawaiResource::getUrl('index'));
        $response->assertStatus(403);
    }

    public function test_daftar_jabatan_dapat_diakses(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $response = $this->get(\App\Filament\Resources\Sdm\JabatanResource::getUrl('index'));
        $response->assertStatus(200);
    }

    public function test_daftar_shift_dapat_diakses(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $response = $this->get(\App\Filament\Resources\Sdm\ShiftKerjaResource::getUrl('index'));
        $response->assertStatus(200);
    }

    public function test_daftar_pelatihan_dapat_diakses(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $response = $this->get(\App\Filament\Resources\Sdm\PelatihanResource::getUrl('index'));
        $response->assertStatus(200);
    }

    public function test_buat_pegawai_via_form(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $response = $this->get(\App\Filament\Resources\Sdm\PegawaiResource::getUrl('create'));
        $response->assertStatus(200);
    }
}
