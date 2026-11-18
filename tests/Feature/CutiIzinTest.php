<?php

namespace Tests\Feature;

use App\Exceptions\Sdm\SdmException;
use App\Models\Cabang;
use App\Models\JenisCuti;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\PengajuanCuti;
use App\Models\PengajuanIzin;
use App\Models\PenugasanPegawai;
use App\Models\SaldoCuti;
use App\Models\User;
use App\Services\Sdm\CutiService;
use App\Services\Sdm\IzinService;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CutiIzinTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;
    private Pegawai $pegawai;
    private PenugasanPegawai $penugasan;
    private User $penyetuju;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferensiSeeder::class);
        $this->cabang = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);

        $orang = Orang::create(['nama_lengkap' => 'Budi', 'jenis_kelamin' => 'L']);
        $this->pegawai = Pegawai::create([
            'orang_id' => $orang->id,
            'nip_yayasan' => 'NIP-BUDI',
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
            'status' => 'aktif',
        ]);
        $jabatan = \App\Models\Jabatan::create([
            'kode' => 'GURU',
            'nama' => 'Guru',
            'kategori' => 'fungsional',
            'is_aktif' => true,
        ]);
        $this->penugasan = PenugasanPegawai::create([
            'pegawai_id' => $this->pegawai->id,
            'cabang_id' => $this->cabang->id,
            'jabatan_id' => $jabatan->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);

        $orangPenyetuju = Orang::create(['nama_lengkap' => 'Penyetuju', 'jenis_kelamin' => 'L']);
        $this->penyetuju = User::create([
            'orang_id' => $orangPenyetuju->id,
            'username' => 'penyetuju',
            'password' => 'pw',
        ]);
        $this->seed(\Database\Seeders\PeranSeeder::class);
        app(\App\Services\PenugasanPeran::class)->berikan($this->penyetuju, \App\Enums\Peran::KepalaCabang, $this->cabang->id);
    }

    public function test_ajukan_cuti_dengan_saldo(): void
    {
        $jenis = JenisCuti::create([
            'kode' => 'CT',
            'nama' => 'Cuti Tahunan',
            'kuota_hari_tahunan' => 12,
        ]);

        $pengajuan = app(CutiService::class)->ajukan(
            $this->pegawai,
            $jenis,
            now()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d'),
            3,
            'Liburan keluarga'
        );

        $this->assertInstanceOf(PengajuanCuti::class, $pengajuan);
        $this->assertEquals('diajukan', $pengajuan->status);
        $this->assertEquals(3, $pengajuan->jumlah_hari);
        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'tahun' => (int) now()->format('Y'),
            'kuota' => 12,
            'terpakai' => 0,
        ]);
    }

    public function test_ajukan_cuti_melebihi_saldo_ditolak(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Sisa cuti tidak mencukupi');

        $jenis = JenisCuti::create([
            'kode' => 'CT',
            'nama' => 'Cuti Tahunan',
            'kuota_hari_tahunan' => 5,
        ]);

        app(CutiService::class)->ajukan(
            $this->pegawai,
            $jenis,
            now()->format('Y-m-d'),
            now()->addDays(10)->format('Y-m-d'),
            10,
            'Liburan panjang'
        );
    }

    public function test_setujui_cuti_memotong_saldo_dan_tandai_absensi(): void
    {
        $jenis = JenisCuti::create([
            'kode' => 'CT',
            'nama' => 'Cuti Tahunan',
            'kuota_hari_tahunan' => 12,
        ]);

        $pengajuan = app(CutiService::class)->ajukan(
            $this->pegawai,
            $jenis,
            now()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d'),
            3,
            'Liburan keluarga'
        );

        $pengajuan = app(CutiService::class)->setujui($pengajuan, $this->penyetuju);

        $this->assertEquals('disetujui', $pengajuan->status);
        $this->assertEquals($this->penyetuju->id, $pengajuan->disetujui_oleh);
        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'terpakai' => 3,
        ]);

        // Absensi 3 hari harus tercatat sebagai cuti
        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => now()->toDateString(),
            'status' => 'cuti',
        ]);
        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => now()->addDays(2)->toDateString(),
            'status' => 'cuti',
        ]);
    }

    public function test_tolak_cuti_tidak_memotong_saldo(): void
    {
        $jenis = JenisCuti::create([
            'kode' => 'CT',
            'nama' => 'Cuti Tahunan',
            'kuota_hari_tahunan' => 12,
        ]);

        $pengajuan = app(CutiService::class)->ajukan(
            $this->pegawai,
            $jenis,
            now()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d'),
            3,
            'Liburan keluarga'
        );

        $pengajuan = app(CutiService::class)->tolak($pengajuan, $this->penyetuju, 'Banyak tugas');

        $this->assertEquals('ditolak', $pengajuan->status);
        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'terpakai' => 0,
        ]);
        $this->assertDatabaseMissing('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'status' => 'cuti',
        ]);
    }

    public function test_ajukan_dan_setujui_izin_sakit(): void
    {
        $pengajuan = app(IzinService::class)->ajukan(
            $this->pegawai,
            'sakit',
            now()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d'),
            'Demam tinggi'
        );

        $this->assertEquals('diajukan', $pengajuan->status);

        $pengajuan = app(IzinService::class)->setujui($pengajuan, $this->penyetuju);

        $this->assertEquals('disetujui', $pengajuan->status);
        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => now()->toDateString(),
            'status' => 'sakit',
        ]);
        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => now()->addDay()->toDateString(),
            'status' => 'sakit',
        ]);
    }

    public function test_batalkan_cuti_yang_sudah_disetujui_mengembalikan_saldo(): void
    {
        $jenis = JenisCuti::create([
            'kode' => 'CT',
            'nama' => 'Cuti Tahunan',
            'kuota_hari_tahunan' => 12,
        ]);

        $pengajuan = app(CutiService::class)->ajukan(
            $this->pegawai,
            $jenis,
            now()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d'),
            3,
            'Liburan keluarga'
        );

        $pengajuan = app(CutiService::class)->setujui($pengajuan, $this->penyetuju);
        $this->assertEquals('disetujui', $pengajuan->status);

        $pengajuan = app(CutiService::class)->batalkan($pengajuan);

        $this->assertEquals('dibatalkan', $pengajuan->status);
        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'terpakai' => 0,
        ]);
        $this->assertDatabaseMissing('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'status' => 'cuti',
        ]);
    }
}
