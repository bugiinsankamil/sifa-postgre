<?php

namespace Tests\Feature;

use App\Exceptions\Sdm\SdmException;
use App\Models\Cabang;
use App\Models\KontrakKerja;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PegawaiRiwayatStatus;
use App\Models\ShiftKerja;
use App\Services\Sdm\KontrakKerjaService;
use App\Services\Sdm\PelatihanService;
use App\Services\Sdm\ShiftKerjaService;
use App\Services\Sdm\StatusKepegawaianService;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SdmTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferensiSeeder::class);
        $this->cabang = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);
    }

    private function pegawai(string $nama): Pegawai
    {
        $orang = Orang::create(['nama_lengkap' => $nama, 'jenis_kelamin' => 'L']);
        return Pegawai::create([
            'orang_id' => $orang->id,
            'nip_yayasan' => 'NIP-'.str_replace(' ', '', $nama),
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
            'status' => 'aktif',
        ]);
    }

    public function test_mengubah_status_kepegawaian_pegawai(): void
    {
        $pegawai = $this->pegawai('Budi');
        $riwayat = app(StatusKepegawaianService::class)->ubahStatus(
            $pegawai, 'kontrak', '2027-01-01', 'SK-002', 'Perubahan status per 2027'
        );
        $this->assertInstanceOf(PegawaiRiwayatStatus::class, $riwayat);
        $this->assertEquals('kontrak', $pegawai->fresh()->status_kepegawaian_ref);
        $this->assertDatabaseHas('pegawai_riwayat_status', [
            'pegawai_id' => $pegawai->id,
            'status_kepegawaian_ref' => 'kontrak',
            'tmt' => '2027-01-01',
            'no_sk' => 'SK-002',
        ]);
    }

    public function test_mengubah_status_sama_ditolak(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('sudah tetap');
        $pegawai = $this->pegawai('Budi');
        app(StatusKepegawaianService::class)->ubahStatus($pegawai, 'tetap', today()->toDateString());
    }

    public function test_buat_kontrak_aktif(): void
    {
        $pegawai = $this->pegawai('Budi');
        $kontrak = app(KontrakKerjaService::class)->buat($pegawai, [
            'nomor' => 'KONT-001',
            'jenis' => 'pkwt',
            'tanggal_mulai' => '2027-01-01',
            'tanggal_selesai' => '2027-12-31',
            'gaji_pokok' => 5000000,
        ]);
        $this->assertInstanceOf(KontrakKerja::class, $kontrak);
        $this->assertEquals('aktif', $kontrak->status);
    }

    public function test_tidak_boleh_dua_kontrak_aktif(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('masih memiliki kontrak aktif');
        $pegawai = $this->pegawai('Budi');
        app(KontrakKerjaService::class)->buat($pegawai, ['nomor' => 'K1', 'jenis' => 'pkwt', 'tanggal_mulai' => today()->toDateString()]);
        app(KontrakKerjaService::class)->buat($pegawai, ['nomor' => 'K2', 'jenis' => 'pkwt', 'tanggal_mulai' => today()->toDateString()]);
    }

    public function test_perpanjang_kontrak_menutup_lama_dan_buat_baru(): void
    {
        $pegawai = $this->pegawai('Budi');
        $kontrakLama = app(KontrakKerjaService::class)->buat($pegawai, [
            'nomor' => 'KONT-001',
            'jenis' => 'pkwt',
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->addMonths(6)->toDateString(),
        ]);
        $kontrakBaru = app(KontrakKerjaService::class)->perpanjang($kontrakLama, [
            'nomor' => 'KONT-002',
            'jenis' => 'pkwt',
            'tanggal_mulai' => today()->addMonths(6)->addDay()->toDateString(),
            'tanggal_selesai' => today()->addYear()->toDateString(),
        ]);
        $this->assertEquals('diperpanjang', $kontrakLama->fresh()->status);
        $this->assertEquals('aktif', $kontrakBaru->status);
        $this->assertEquals(2, KontrakKerja::where('pegawai_id', $pegawai->id)->count());
    }

    public function test_tetapkan_shift_pegawai_menutup_shift_lama(): void
    {
        $pegawai = $this->pegawai('Budi');
        $shiftPagi = ShiftKerja::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Pagi',
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'hari_kerja' => [1, 2, 3, 4, 5],
        ]);
        $shiftSiang = ShiftKerja::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Siang',
            'jam_masuk' => '13:00',
            'jam_pulang' => '21:00',
            'hari_kerja' => [1, 2, 3, 4, 5],
        ]);
        $penugasan1 = app(ShiftKerjaService::class)->tetapkan($pegawai, $shiftPagi, '2027-01-01');
        $this->assertNull($penugasan1->tanggal_akhir);
        $penugasan2 = app(ShiftKerjaService::class)->tetapkan($pegawai, $shiftSiang, '2027-02-01');
        $this->assertEquals('2027-01-31', $penugasan1->fresh()->tanggal_akhir->toDateString());
        $this->assertNull($penugasan2->tanggal_akhir);
    }

    public function test_daftar_peserta_pelatihan(): void
    {
        $pegawai = $this->pegawai('Budi');
        $pelatihan = Pelatihan::create([
            'cabang_id' => $this->cabang->id,
            'judul' => 'Pelatihan Test',
            'jenis_ref' => 'internal',
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->addDays(2)->toDateString(),
        ]);
        $peserta = app(PelatihanService::class)->daftarkan($pelatihan, $pegawai);
        $this->assertInstanceOf(PelatihanPeserta::class, $peserta);
        $this->assertEquals('terdaftar', $peserta->status);
    }

    public function test_catat_hasil_pelatihan(): void
    {
        $pegawai = $this->pegawai('Budi');
        $pelatihan = Pelatihan::create([
            'cabang_id' => $this->cabang->id,
            'judul' => 'Pelatihan Test',
            'jenis_ref' => 'internal',
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->addDays(2)->toDateString(),
        ]);
        $peserta = app(PelatihanService::class)->daftarkan($pelatihan, $pegawai);
        $hasil = app(PelatihanService::class)->catatHasil($peserta, 'lulus', 85.50, 'CERT-001');
        $this->assertEquals('lulus', $hasil->status);
        $this->assertEquals('85.50', $hasil->nilai);
        $this->assertEquals('CERT-001', $hasil->nomor_sertifikat);
    }
}
