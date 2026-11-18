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

    // ============ Paket A: form tersambung ke layanan ============

    public function test_create_absensi_tersimpan_lewat_service(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $pegawai = $this->pegawai();

        // Buat penugasan agar absensi bisa dicatat
        $jabatan = \App\Models\Jabatan::create(['kode' => 'GURU', 'nama' => 'Guru', 'kategori' => 'fungsional', 'is_aktif' => true]);
        \App\Models\PenugasanPegawai::create([
            'pegawai_id' => $pegawai->id,
            'cabang_id' => $this->cabang->id,
            'jabatan_id' => $jabatan->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);

        \Livewire\Livewire::test(\App\Filament\Resources\Sdm\Pages\CreateAbsensi::class)
            ->fillForm([
                'pegawai_id' => $pegawai->id,
                'tanggal' => today()->toDateString(),
                'status' => 'hadir',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $pegawai->id,
            'tanggal' => today()->toDateString(),
            'status' => 'hadir',
        ]);
    }

    public function test_create_cuti_tersimpan_lewat_service(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $pegawai = $this->pegawai();

        $jabatan = \App\Models\Jabatan::create(['kode' => 'GURU', 'nama' => 'Guru', 'kategori' => 'fungsional', 'is_aktif' => true]);
        \App\Models\PenugasanPegawai::create([
            'pegawai_id' => $pegawai->id,
            'cabang_id' => $this->cabang->id,
            'jabatan_id' => $jabatan->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);

        $jenis = \App\Models\JenisCuti::create([
            'kode' => 'CT',
            'nama' => 'Cuti Tahunan',
            'kuota_hari_tahunan' => 12,
        ]);

        \Livewire\Livewire::test(\App\Filament\Resources\Sdm\Pages\CreatePengajuanCuti::class)
            ->fillForm([
                'pegawai_id' => $pegawai->id,
                'jenis_cuti_id' => $jenis->id,
                'tanggal_mulai' => today()->toDateString(),
                'tanggal_selesai' => today()->addDays(2)->toDateString(),
                'jumlah_hari' => 3,
                'alasan' => 'Liburan keluarga',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pengajuan_cuti', [
            'pegawai_id' => $pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'status' => 'diajukan',
        ]);
    }

    public function test_create_cuti_melebihi_saldo_menampilkan_error(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $pegawai = $this->pegawai();

        $jabatan = \App\Models\Jabatan::create(['kode' => 'GURU', 'nama' => 'Guru', 'kategori' => 'fungsional', 'is_aktif' => true]);
        \App\Models\PenugasanPegawai::create([
            'pegawai_id' => $pegawai->id,
            'cabang_id' => $this->cabang->id,
            'jabatan_id' => $jabatan->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);

        $jenis = \App\Models\JenisCuti::create([
            'kode' => 'CT',
            'nama' => 'Cuti Tahunan',
            'kuota_hari_tahunan' => 5,
        ]);

        // Exception domain ditangkap halaman (notifikasi), tidak bocor sebagai error 500
        \Livewire\Livewire::test(\App\Filament\Resources\Sdm\Pages\CreatePengajuanCuti::class)
            ->fillForm([
                'pegawai_id' => $pegawai->id,
                'jenis_cuti_id' => $jenis->id,
                'tanggal_mulai' => today()->toDateString(),
                'tanggal_selesai' => today()->addDays(10)->toDateString(),
                'jumlah_hari' => 10,
                'alasan' => 'Liburan panjang',
            ])
            ->call('create')
            ->assertNotified('Gagal mengajukan cuti');

        $this->assertDatabaseMissing('pengajuan_cuti', ['pegawai_id' => $pegawai->id]);
    }

    public function test_admin_tidak_bisa_setujui_cuti(): void
    {
        $user = $this->pengguna('admin1', Peran::Admin, $this->cabang->id);
        $this->actingAs($user);
        $pegawai = $this->pegawai();

        $jabatan = \App\Models\Jabatan::create(['kode' => 'GURU', 'nama' => 'Guru', 'kategori' => 'fungsional', 'is_aktif' => true]);
        \App\Models\PenugasanPegawai::create([
            'pegawai_id' => $pegawai->id,
            'cabang_id' => $this->cabang->id,
            'jabatan_id' => $jabatan->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);

        $jenis = \App\Models\JenisCuti::create(['kode' => 'CT', 'nama' => 'Cuti Tahunan', 'kuota_hari_tahunan' => 12]);

        $pengajuan = app(\App\Services\Sdm\CutiService::class)->ajukan(
            $pegawai, $jenis, today()->toDateString(), today()->addDays(2)->toDateString(), 3, 'Liburan'
        );

        $this->assertFalse(\App\Filament\Resources\Sdm\PengajuanCutiResource::canApprovePengajuan());

        // Paksa eksekusi aksi lewat panel: Filament boleh menolak diam-diam atau melempar, status tetap tidak berubah
        try {
            \Livewire\Livewire::test(\App\Filament\Resources\Sdm\Pages\ListPengajuanCuti::class)
                ->callTableAction('setujui', $pengajuan);
        } catch (\Throwable) {
            // penolakan aksi tanpa hak diharapkan
        }

        $this->assertSame('diajukan', $pengajuan->fresh()->status);

        // Service juga menolak walau dipanggil langsung
        $this->expectException(\App\Exceptions\Sdm\SdmException::class);
        app(\App\Services\Sdm\CutiService::class)->setujui($pengajuan, $user);
    }

    public function test_kepala_cabang_bisa_setujui_cuti(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $pegawai = $this->pegawai();

        $jabatan = \App\Models\Jabatan::create(['kode' => 'GURU', 'nama' => 'Guru', 'kategori' => 'fungsional', 'is_aktif' => true]);
        \App\Models\PenugasanPegawai::create([
            'pegawai_id' => $pegawai->id,
            'cabang_id' => $this->cabang->id,
            'jabatan_id' => $jabatan->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);

        $jenis = \App\Models\JenisCuti::create(['kode' => 'CT', 'nama' => 'Cuti Tahunan', 'kuota_hari_tahunan' => 12]);

        $pengajuan = app(\App\Services\Sdm\CutiService::class)->ajukan(
            $pegawai, $jenis, today()->toDateString(), today()->addDays(2)->toDateString(), 3, 'Liburan'
        );

        $this->assertTrue(\App\Filament\Resources\Sdm\PengajuanCutiResource::canApprovePengajuan());

        // Eksekusi persetujuan via service
        app(\App\Services\Sdm\CutiService::class)->setujui($pengajuan, $user);
        $this->assertEquals('disetujui', $pengajuan->fresh()->status);
    }

    public function test_kepala_cabang_bisa_tolak_cuti(): void
    {
        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->cabang->id);
        $this->actingAs($user);
        $pegawai = $this->pegawai();

        $jabatan = \App\Models\Jabatan::create(['kode' => 'GURU', 'nama' => 'Guru', 'kategori' => 'fungsional', 'is_aktif' => true]);
        \App\Models\PenugasanPegawai::create([
            'pegawai_id' => $pegawai->id,
            'cabang_id' => $this->cabang->id,
            'jabatan_id' => $jabatan->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);

        $jenis = \App\Models\JenisCuti::create(['kode' => 'CT', 'nama' => 'Cuti Tahunan', 'kuota_hari_tahunan' => 12]);

        $pengajuan = app(\App\Services\Sdm\CutiService::class)->ajukan(
            $pegawai, $jenis, today()->toDateString(), today()->addDays(2)->toDateString(), 3, 'Liburan'
        );

        app(\App\Services\Sdm\CutiService::class)->tolak($pengajuan, $user, 'Banyak tugas');
        $this->assertEquals('ditolak', $pengajuan->fresh()->status);
    }
}
