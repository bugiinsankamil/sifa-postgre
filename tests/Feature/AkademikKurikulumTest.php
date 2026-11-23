<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Exceptions\Akademik\KurikulumException;
use App\Models\Cabang;
use App\Models\Jenjang;
use App\Models\Kurikulum;
use App\Models\KurikulumSekolah;
use App\Models\MataPelajaran;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\StrukturKurikulum;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Services\Akademik\KurikulumService;
use App\Services\PenugasanPeran;
use Database\Seeders\JenjangSeeder;
use Database\Seeders\PeranSeeder;
use Database\Seeders\TingkatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkademikKurikulumTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;
    private Sekolah $sekolah;
    private Jenjang $jenjang;
    private TahunAjaran $tahunAjaran;
    private Tingkat $tingkat;
    private User $sysadmin;
    private User $kepalaCabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JenjangSeeder::class);
        $this->seed(TingkatSeeder::class);
        $this->seed(PeranSeeder::class);

        $this->cabang = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);
        $this->jenjang = Jenjang::where('kode', 'SD')->first();
        $this->sekolah = Sekolah::create([
            'cabang_id' => $this->cabang->id,
            'jenjang_id' => $this->jenjang->id,
            'nama' => 'SD Fathona',
            'npsn' => '12345678',
            'status' => 'aktif',
        ]);
        $this->tahunAjaran = TahunAjaran::create([
            'kode' => '2026/2027',
            'nama' => 'Tahun Ajaran 2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'is_aktif' => true,
        ]);
        $this->tingkat = Tingkat::where('jenjang_id', $this->jenjang->id)
            ->where('kode', '1')->first();

        $orangSysadmin = Orang::create(['nama_lengkap' => 'Sysadmin', 'jenis_kelamin' => 'L']);
        $this->sysadmin = User::create([
            'orang_id' => $orangSysadmin->id,
            'username' => 'sysadmin',
            'password' => 'pw',
        ]);
        app(PenugasanPeran::class)->berikan($this->sysadmin, Peran::Sysadmin);

        $orangKepala = Orang::create(['nama_lengkap' => 'Kepala Cabang', 'jenis_kelamin' => 'L']);
        $this->kepalaCabang = User::create([
            'orang_id' => $orangKepala->id,
            'username' => 'kepala_cabang',
            'password' => 'pw',
        ]);
        app(PenugasanPeran::class)->berikan($this->kepalaCabang, Peran::KepalaCabang, $this->cabang->id);
    }

    private function pengguna(string $username, Peran $peran, ?int $cabangId = null): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'pw']);
        app(PenugasanPeran::class)->berikan($user, $peran, $cabangId);
        return $user;
    }

    private function buatKurikulum(string $kode, string $nama): Kurikulum
    {
        return Kurikulum::create(['kode' => $kode, 'nama' => $nama, 'is_aktif' => true]);
    }

    public function test_sysadmin_bisa_buat_kurikulum(): void
    {
        $this->actingAs($this->sysadmin);
        $kurikulum = $this->buatKurikulum('KM', 'Kurikulum Merdeka');

        $this->assertDatabaseHas('kurikulum', ['kode' => 'KM', 'nama' => 'Kurikulum Merdeka']);
        $this->assertTrue($kurikulum->is_aktif);
    }

    public function test_kode_kurikulum_harus_unik(): void
    {
        $this->buatKurikulum('KM', 'Kurikulum Merdeka');

        $this->expectException(\Illuminate\Database\QueryException::class);
        Kurikulum::create(['kode' => 'KM', 'nama' => 'Lain', 'is_aktif' => true]);
    }

    public function test_kurikulum_sudah_diterapkan_tidak_boleh_dihapus(): void
    {
        $kurikulum = $this->buatKurikulum('KM', 'Kurikulum Merdeka');

        app(KurikulumService::class)->terapkan(
            $kurikulum, $this->sekolah, $this->tahunAjaran, null, $this->sysadmin
        );

        $this->expectException(KurikulumException::class);
        $this->expectExceptionMessage('sudah diterapkan ke sekolah');

        app(KurikulumService::class)->nonaktifkan(
            KurikulumSekolah::query()->where('kurikulum_id', $kurikulum->id)->first(),
            $this->sysadmin
        );
    }

    public function test_nonaktifkan_penerapan_tidak_menghapus_histori(): void
    {
        $kurikulum = $this->buatKurikulum('KM', 'Kurikulum Merdeka');
        $penerapan = app(KurikulumService::class)->terapkan(
            $kurikulum, $this->sekolah, $this->tahunAjaran, null, $this->sysadmin
        );

        $hasil = app(KurikulumService::class)->nonaktifkan($penerapan, $this->sysadmin);

        $this->assertFalse($hasil->is_aktif);
        $this->assertDatabaseHas('kurikulum_sekolah', [
            'id' => $penerapan->id,
            'is_aktif' => false,
        ]);
    }
    }