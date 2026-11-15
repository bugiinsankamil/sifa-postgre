<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\PenugasanPeran;
use Database\Seeders\PeranSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class ArsipTest extends TestCase
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
    }

    private function pengguna(string $username, Peran $peran, ?int $cabangId = null): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'rahasia-uji-123']);
        app(PenugasanPeran::class)->berikan($user, $peran, $cabangId);

        return $user;
    }

    public function test_admin_cabang_melihat_surat_masuk_cabangnya_saja(): void
    {
        SuratMasuk::create([
            'cabang_id' => $this->a['cabang'], 'nomor_agenda' => 'SM-001',
            'tanggal_terima' => today(), 'pengirim' => 'Dinas Pendidikan',
            'perihal' => 'Undangan rapat', 'sifat' => 'penting', 'status' => 'diterima',
        ]);
        SuratMasuk::create([
            'cabang_id' => $this->b['cabang'], 'nomor_agenda' => 'SM-002',
            'tanggal_terima' => today(), 'pengirim' => 'Dinas Pendidikan',
            'perihal' => 'Undangan rapat', 'sifat' => 'penting', 'status' => 'diterima',
        ]);

        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(\App\Filament\Resources\Arsip\SuratMasukResource::getUrl('index'))
            ->assertOk()
            ->assertSee('SM-001')
            ->assertDontSee('SM-002');
    }

    public function test_admin_cabang_melihat_surat_keluar_cabangnya_saja(): void
    {
        $pegawai = Pegawai::create([
            'orang_id' => Orang::create(['nama_lengkap' => 'Budi', 'jenis_kelamin' => 'L'])->id,
            'nip_yayasan' => 'NIP-001', 'tipe' => 'staf', 'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
        ]);
        SuratKeluar::create([
            'cabang_id' => $this->a['cabang'], 'nomor_surat' => 'SK-001',
            'tanggal_surat' => today(), 'tujuan' => 'Orang Tua',
            'perihal' => 'Pemberitahuan', 'jenis' => 'biasa', 'isi' => 'Isi surat',
            'pembuat_id' => $pegawai->id, 'status' => 'draft',
        ]);
        SuratKeluar::create([
            'cabang_id' => $this->b['cabang'], 'nomor_surat' => 'SK-002',
            'tanggal_surat' => today(), 'tujuan' => 'Orang Tua',
            'perihal' => 'Pemberitahuan', 'jenis' => 'biasa', 'isi' => 'Isi surat',
            'pembuat_id' => $pegawai->id, 'status' => 'draft',
        ]);

        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(\App\Filament\Resources\Arsip\SuratKeluarResource::getUrl('index'))
            ->assertOk()
            ->assertSee('SK-001')
            ->assertDontSee('SK-002');
    }

    public function test_nomor_surat_keluar_dibuat_otomatis(): void
    {
        $pegawai = Pegawai::create([
            'orang_id' => Orang::create(['nama_lengkap' => 'Budi', 'jenis_kelamin' => 'L'])->id,
            'nip_yayasan' => 'NIP-001', 'tipe' => 'staf', 'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
        ]);

        $surat = SuratKeluar::create([
            'cabang_id' => $this->a['cabang'], 'tanggal_surat' => today(),
            'tujuan' => 'Orang Tua', 'perihal' => 'Pemberitahuan',
            'jenis' => 'biasa', 'isi' => 'Isi surat', 'pembuat_id' => $pegawai->id,
        ]);

        $this->assertStringStartsWith('SK/FTN-01/', $surat->nomor_surat);
    }
}
