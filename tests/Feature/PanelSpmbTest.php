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

    public function test_admin_cabang_melihat_kontak_prospek_cabangnya_saja(): void
    {
        \App\Models\SpmbKontak::create([
            'cabang_id' => $this->a['cabang'], 'tahun_ajaran_id' => $this->a['tahun'],
            'nama_kontak' => 'Kontak Cabang Satu', 'telepon' => '0812-1111-1111',
            'sumber_ref' => 'website', 'status' => 'baru',
        ]);
        \App\Models\SpmbKontak::create([
            'cabang_id' => $this->b['cabang'], 'tahun_ajaran_id' => $this->b['tahun'],
            'nama_kontak' => 'Kontak Cabang Dua', 'telepon' => '0812-2222-2222',
            'sumber_ref' => 'website', 'status' => 'baru',
        ]);

        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(\App\Filament\Resources\Spmb\SpmbKontakResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Kontak Cabang Satu')
            ->assertDontSee('Kontak Cabang Dua');
    }

    public function test_admin_cabang_melihat_kampanye_cabangnya_saja(): void
    {
        \App\Models\SpmbKampanye::create([
            'cabang_id' => $this->a['cabang'], 'nama' => 'Kampanye Cabang Satu',
            'kanal_ref' => 'instagram', 'tanggal_mulai' => today(),
        ]);
        \App\Models\SpmbKampanye::create([
            'cabang_id' => $this->b['cabang'], 'nama' => 'Kampanye Cabang Dua',
            'kanal_ref' => 'instagram', 'tanggal_mulai' => today(),
        ]);

        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(\App\Filament\Resources\Spmb\SpmbKampanyeResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Kampanye Cabang Satu')
            ->assertDontSee('Kampanye Cabang Dua');
    }

    public function test_admin_cabang_melihat_kegiatan_cabangnya_saja(): void
    {
        \App\Models\SpmbKegiatan::create([
            'cabang_id' => $this->a['cabang'], 'nama' => 'Open House Cabang Satu',
            'jenis' => 'open_house', 'tanggal' => today(),
        ]);
        \App\Models\SpmbKegiatan::create([
            'cabang_id' => $this->b['cabang'], 'nama' => 'Open House Cabang Dua',
            'jenis' => 'open_house', 'tanggal' => today(),
        ]);

        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(\App\Filament\Resources\Spmb\SpmbKegiatanResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Open House Cabang Satu')
            ->assertDontSee('Open House Cabang Dua');
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

    public function test_tetapkan_hasil_memperbarui_status_pendaftar(): void
    {
        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $this->actingAs($user);
        $p = \App\Models\SpmbPendaftar::query()->where('cabang_id', $this->a['cabang'])->first();
        $p->update(['status' => 'seleksi']);

        \Livewire\Livewire::test(\App\Filament\Resources\Spmb\Pages\ViewSpmbPendaftar::class, ['record' => $p->getRouteKey()])
            ->callAction('hasil', ['keputusan' => 'lulus', 'catatan' => 'Memenuhi syarat']);

        $this->assertSame('lulus', $p->fresh()->status);
        $this->assertDatabaseHas('spmb_hasil', ['pendaftar_id' => $p->id, 'keputusan' => 'lulus']);
    }

    public function test_daftar_ulang_selesai_membuat_record_daftar_ulang(): void
    {
        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $this->actingAs($user);
        $p = \App\Models\SpmbPendaftar::query()->where('cabang_id', $this->a['cabang'])->first();
        $p->update(['status' => 'daftar_ulang']);
        \App\Models\SpmbDaftarUlang::create(['pendaftar_id' => $p->id, 'status' => 'menunggu']);

        \Livewire\Livewire::test(\App\Filament\Resources\Spmb\Pages\ViewSpmbPendaftar::class, ['record' => $p->getRouteKey()])
            ->callAction('selesaiDaftarUlang', ['tanggal' => today()->toDateString()]);

        $this->assertSame('selesai', $p->daftarUlang()->first()->status);
    }

    public function test_verifikasi_action_di_halaman_detail(): void
    {
        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $this->actingAs($user);
        $p = \App\Models\SpmbPendaftar::query()->where('cabang_id', $this->a['cabang'])->first();
        \Illuminate\Support\Facades\DB::table('spmb_berkas')->insert([
            'pendaftar_id' => $p->id, 'persyaratan_id' => $this->a['kk'], 'periode_id' => $this->a['periode']->id,
            'status_verifikasi' => 'valid', 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('spmb_berkas')->insert([
            'pendaftar_id' => $p->id, 'persyaratan_id' => $this->a['akta'], 'periode_id' => $this->a['periode']->id,
            'status_verifikasi' => 'valid', 'created_at' => now(), 'updated_at' => now(),
        ]);

        \Livewire\Livewire::test(\App\Filament\Resources\Spmb\Pages\ViewSpmbPendaftar::class, ['record' => $p->getRouteKey()])
            ->callAction('verifikasi');

        $this->assertSame('diverifikasi', $p->fresh()->status);
    }

    public function test_koreksi_biodata_mengubah_nama_pendaftar(): void
    {
        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $this->actingAs($user);
        $p = \App\Models\SpmbPendaftar::query()->where('cabang_id', $this->a['cabang'])->first();

        \Livewire\Livewire::test(\App\Filament\Resources\Spmb\Pages\EditSpmbPendaftar::class, ['record' => $p->getRouteKey()])
            ->fillForm(['nama_lengkap' => 'Nama Dikoreksi'])
            ->call('save');

        $this->assertSame('Nama Dikoreksi', $p->fresh()->nama_lengkap);
    }

    public function test_kirim_ulang_pesan_gagal_mengembalikan_status_antri(): void
    {
        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $this->actingAs($user);
        $pesan = \App\Models\AntreanPesan::create([
            'cabang_id' => $this->a['cabang'], 'kanal' => 'whatsapp', 'tujuan' => '6281234567890',
            'isi' => 'Test', 'status' => 'gagal',
        ]);

        \Livewire\Livewire::test(\App\Filament\Resources\Pesan\Pages\ListAntreanPesan::class)
            ->callAction(\Filament\Actions\Testing\TestAction::make('kirimUlang')->table($pesan));

        $this->assertSame('antri', $pesan->fresh()->status);
    }

    public function test_batalkan_pesan_antri_mengubah_status_dibatalkan(): void
    {
        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $this->actingAs($user);
        $pesan = \App\Models\AntreanPesan::create([
            'cabang_id' => $this->a['cabang'], 'kanal' => 'whatsapp', 'tujuan' => '6281234567890',
            'isi' => 'Test', 'status' => 'antri',
        ]);

        \Livewire\Livewire::test(\App\Filament\Resources\Pesan\Pages\ListAntreanPesan::class)
            ->callAction(\Filament\Actions\Testing\TestAction::make('batalkan')->table($pesan));

        $this->assertSame('dibatalkan', $pesan->fresh()->status);
    }
}
