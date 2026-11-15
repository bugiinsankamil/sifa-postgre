<?php

namespace Tests\Feature;

use App\Exceptions\Sdm\SdmException;
use App\Models\Cabang;
use App\Models\Jabatan;
use App\Models\KpiIndikator;
use App\Models\KpiPenilaian;
use App\Models\KpiPeriode;
use App\Models\KpiTemplat;
use App\Models\KpiTemplatIndikator;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Services\Sdm\KpiService;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiTest extends TestCase
{
    use RefreshDatabase;

    private KpiPeriode $periode;
    private Pegawai $pegawai;
    private Pegawai $penilai;
    private KpiTemplat $templat;
    private KpiIndikator $indikator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferensiSeeder::class);

        $cabang = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);

        $this->periode = KpiPeriode::create([
            'nama' => 'Q4 2026',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-12-31',
            'cabang_id' => $cabang->id,
        ]);

        $this->indikator = KpiIndikator::create([
            'kode' => 'KHD',
            'nama' => 'Kehadiran',
            'satuan' => '%',
            'arah' => 'semakin_tinggi',
        ]);

        $this->templat = KpiTemplat::create([
            'nama' => 'Templat Guru',
            'tipe_pegawai' => 'guru',
        ]);

        KpiTemplatIndikator::create([
            'templat_id' => $this->templat->id,
            'indikator_id' => $this->indikator->id,
            'bobot' => 100,
            'target' => 90,
        ]);

        $jabatan = Jabatan::create(['kode' => 'GURU', 'nama' => 'Guru', 'kategori' => 'fungsional', 'is_aktif' => true]);

        $orang1 = Orang::create(['nama_lengkap' => 'Budi', 'jenis_kelamin' => 'L']);
        $this->pegawai = Pegawai::create([
            'orang_id' => $orang1->id,
            'nip_yayasan' => 'NIP-BUDI',
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
            'status' => 'aktif',
        ]);

        $orang2 = Orang::create(['nama_lengkap' => 'Kepala', 'jenis_kelamin' => 'L']);
        $this->penilai = Pegawai::create([
            'orang_id' => $orang2->id,
            'nip_yayasan' => 'NIP-KEPALA',
            'tipe' => 'pimpinan',
            'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
            'status' => 'aktif',
        ]);
    }

    public function test_buat_penilaian_dari_templat(): void
    {
        $penilaian = app(KpiService::class)->buatPenilaian($this->periode, $this->pegawai, $this->penilai, $this->templat);

        $this->assertInstanceOf(KpiPenilaian::class, $penilaian);
        $this->assertEquals('draft', $penilaian->status);
        $this->assertCount(1, $penilaian->detail); // 1 indikator dari templat
        $this->assertEquals(100, $penilaian->detail->first()->bobot);
        $this->assertEquals(90, $penilaian->detail->first()->target);
    }

    public function test_duplikasi_penilaian_per_periode_ditolak(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('sudah ada');

        app(KpiService::class)->buatPenilaian($this->periode, $this->pegawai, $this->penilai, $this->templat);
        app(KpiService::class)->buatPenilaian($this->periode, $this->pegawai, $this->penilai, $this->templat);
    }

    public function test_isi_realisasi_dan_hitung_skor(): void
    {
        $penilaian = app(KpiService::class)->buatPenilaian($this->periode, $this->pegawai, $this->penilai, $this->templat);

        $detail = $penilaian->detail->first();

        // Realisasi 85% dari target 90% -> skor 85/90 = 94.44
        app(KpiService::class)->isiRealisasi($detail, 85.0);
        $detail = app(KpiService::class)->hitungSkor($detail->fresh());

        $this->assertEqualsWithDelta(94.44, (float) $detail->skor, 0.01);
    }

    public function test_finalisasi_penilaian_menghitung_skor_akhir(): void
    {
        $penilaian = app(KpiService::class)->buatPenilaian($this->periode, $this->pegawai, $this->penilai, $this->templat);

        $detail = $penilaian->detail->first();
        app(KpiService::class)->isiRealisasi($detail, 90.0); // = target → skor = 100

        $penilaian = app(KpiService::class)->finalisasi($penilaian->fresh(['detail']));

        $this->assertEquals('dinilai', $penilaian->status);
        $this->assertEquals(100.0, (float) $penilaian->skor_akhir);
        $this->assertEquals('A', $penilaian->predikat);
    }

    public function test_setujui_penilaian(): void
    {
        $penilaian = app(KpiService::class)->buatPenilaian($this->periode, $this->pegawai, $this->penilai, $this->templat);

        $detail = $penilaian->detail->first();
        app(KpiService::class)->isiRealisasi($detail, 70.0); // skor = 77.78 -> B

        $penilaian = app(KpiService::class)->finalisasi($penilaian->fresh(['detail']));
        $penilaian = app(KpiService::class)->setujui($penilaian);

        $this->assertEquals('disetujui', $penilaian->status);
        $this->assertEquals('B', $penilaian->predikat);
    }

    public function test_finalisasi_tanpa_realisasi_otomatis_mengisi_skor(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Realisasi belum diisi');

        $penilaian = app(KpiService::class)->buatPenilaian($this->periode, $this->pegawai, $this->penilai, $this->templat);
        // Tidak isi realisasi sama sekali, finalisasi harus memanggil hitungSkor yang akan gagal
        app(KpiService::class)->finalisasi($penilaian->fresh(['detail']));
    }
}
