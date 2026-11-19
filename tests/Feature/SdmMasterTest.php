<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Cabang;
use App\Models\HariLibur;
use App\Models\JenisCuti;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\PenugasanPegawai;
use App\Models\SaldoCuti;
use App\Models\User;
use App\Services\PenugasanPeran;
use App\Services\Sdm\MutasiSaldoCutiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SdmMasterTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $a;
    private Cabang $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferensiSeeder::class);
        $this->seed(\Database\Seeders\PeranSeeder::class);
        $this->a = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);
        $this->b = Cabang::create(['kode' => 'FTN-02', 'nama' => 'Fathona 02']);
    }

    private function pengguna(string $username, Peran $peran, ?int $cabangId = null): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'pw']);
        app(PenugasanPeran::class)->berikan($user, $peran, $cabangId);
        return $user;
    }
    private function pegawai(string $nama, Cabang $cabang): Pegawai
    {
        $orang = Orang::create(['nama_lengkap' => $nama, 'jenis_kelamin' => 'L']);
        $pegawai = Pegawai::create([
            'orang_id' => $orang->id,
            'nip_yayasan' => 'NIP-'.strtoupper(substr($nama, 0, 3)).'-'.strtoupper(substr($cabang->kode, -2)),
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
            'status' => 'aktif',
        ]);
        $jabatan = \App\Models\Jabatan::firstOrCreate(
            ['kode' => 'GURU-'.$cabang->kode],
            ['nama' => 'Guru', 'kategori' => 'fungsional', 'is_aktif' => true]
        );
        PenugasanPegawai::create([
            'pegawai_id' => $pegawai->id,
            'cabang_id' => $cabang->id,
            'jabatan_id' => $jabatan->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);
        return $pegawai;
    }

    public function test_sysadmin_bisa_akses_daftar_jenis_cuti(): void
    {
        $this->actingAs($this->pengguna('sa', Peran::Sysadmin));
        JenisCuti::create(['kode' => 'CT', 'nama' => 'Cuti Tahunan', 'kuota_hari_tahunan' => 12]);
        $response = $this->get(\App\Filament\Resources\Sdm\JenisCutiResource::getUrl('index'));
        $response->assertStatus(200);
        $response->assertSee('Cuti Tahunan');
    }

    public function test_jenis_cuti_tidak_boleh_dihapus(): void
    {
        $this->actingAs($this->pengguna('sa', Peran::Sysadmin));
        $this->assertFalse(\App\Filament\Resources\Sdm\JenisCutiResource::canDelete(new JenisCuti));
    }

    public function test_saldo_cuti_hanya_terlihat_cabang_sama(): void
    {
        $pa = $this->pegawai('Pegawai A', $this->a);
        $pb = $this->pegawai('Pegawai B', $this->b);
        $jenis = JenisCuti::create(['kode' => 'CT', 'nama' => 'Cuti Tahunan', 'kuota_hari_tahunan' => 12]);
        SaldoCuti::create(['pegawai_id' => $pa->id, 'jenis_cuti_id' => $jenis->id, 'tahun' => 2026, 'kuota' => 12, 'terpakai' => 2, 'dibawa' => 0]);
        SaldoCuti::create(['pegawai_id' => $pb->id, 'jenis_cuti_id' => $jenis->id, 'tahun' => 2026, 'kuota' => 12, 'terpakai' => 0, 'dibawa' => 0]);

        $this->actingAs($this->pengguna('kepala1', Peran::KepalaCabang, $this->a->id));
        $response = $this->get(\App\Filament\Resources\Sdm\SaldoCutiResource::getUrl('index'));
        $response->assertStatus(200);
        $response->assertSee('Pegawai A');
        $response->assertDontSee('Pegawai B');
    }

    public function test_saldo_cuti_read_only_dan_sesuaikan_via_service(): void
    {
        $this->assertFalse(\App\Filament\Resources\Sdm\SaldoCutiResource::canCreate(new SaldoCuti));
        $this->assertFalse(\App\Filament\Resources\Sdm\SaldoCutiResource::canEdit(new SaldoCuti));
        $this->assertFalse(\App\Filament\Resources\Sdm\SaldoCutiResource::canDelete(new SaldoCuti));

        $pegawai = $this->pegawai('Pegawai C', $this->a);
        $jenis = JenisCuti::create(['kode' => 'CT', 'nama' => 'Cuti Tahunan', 'kuota_hari_tahunan' => 12]);
        $saldo = SaldoCuti::create(['pegawai_id' => $pegawai->id, 'jenis_cuti_id' => $jenis->id, 'tahun' => 2026, 'kuota' => 12, 'terpakai' => 3, 'dibawa' => 0]);

        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->a->id);
        app(MutasiSaldoCutiService::class)->sesuaikan($saldo, '10.0', 'Perbaikan kuota awal', $user);

        $this->assertEquals('10.0', $saldo->fresh()->sisa());
        $this->assertDatabaseHas('mutasi_saldo_cuti', [
            'pegawai_id' => $pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'tahun' => 2026,
            'tipe' => 'penyesuaian',
            'saldo_sesudah' => '10.0',
        ]);
    }

    public function test_sesuaikan_saldo_ditolak_untuk_cabang_lain(): void
    {
        $pegawai = $this->pegawai('Pegawai D', $this->b);
        $jenis = JenisCuti::create(['kode' => 'CT', 'nama' => 'Cuti Tahunan', 'kuota_hari_tahunan' => 12]);
        $saldo = SaldoCuti::create(['pegawai_id' => $pegawai->id, 'jenis_cuti_id' => $jenis->id, 'tahun' => 2026, 'kuota' => 12, 'terpakai' => 0, 'dibawa' => 0]);

        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->a->id);
        $this->actingAs($user);
        $this->expectException(\App\Exceptions\Sdm\SdmException::class);
        app(MutasiSaldoCutiService::class)->sesuaikan($saldo, '5.0', 'Salah cabang', $user);
    }

    public function test_hari_libur_bisa_diakses_dan_dilindungi_dari_hapus(): void
    {
        $this->actingAs($this->pengguna('sa', Peran::Sysadmin));
        HariLibur::create(['cabang_id' => null, 'tanggal' => '2026-01-01', 'nama' => 'Tahun Baru', 'is_cuti_bersama' => true]);
        $response = $this->get(\App\Filament\Resources\Sdm\HariLiburResource::getUrl('index'));
        $response->assertStatus(200);
        $response->assertSee('Tahun Baru');
        $this->assertFalse(\App\Filament\Resources\Sdm\HariLiburResource::canDelete(new HariLibur));
    }

    public function test_hari_libur_yayasan_terlihat_semua_cabang(): void
    {
        HariLibur::create(['cabang_id' => null, 'tanggal' => '2026-01-01', 'nama' => 'Tahun Baru']);
        HariLibur::create(['cabang_id' => $this->a->id, 'tanggal' => '2026-02-01', 'nama' => 'Libur Cabang A']);

        $this->actingAs($this->pengguna('kepala1', Peran::KepalaCabang, $this->b->id));
        $response = $this->get(\App\Filament\Resources\Sdm\HariLiburResource::getUrl('index'));
        $response->assertStatus(200);
        $response->assertSee('Tahun Baru');
        $response->assertDontSee('Libur Cabang A');
    }
}
