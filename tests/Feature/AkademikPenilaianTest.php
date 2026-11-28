<?php

namespace Tests\Feature;

use App\Models\AturanOlahNilai;
use App\Models\Cabang;
use App\Models\Jenjang;
use App\Models\KomponenPenilaian;
use App\Models\MataPelajaran;
use App\Models\NilaiAkhir;
use App\Models\NilaiSiswa;
use App\Models\Orang;
use App\Models\Penilaian;
use App\Models\PenugasanMengajar;
use App\Models\Rombel;
use App\Models\RombelSiswa;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Enums\Peran;
use App\Exceptions\Akademik\OlahNilaiException;
use App\Exceptions\Akademik\PenilaianException;
use App\Services\Akademik\OlahNilaiService;
use App\Services\Akademik\PenilaianService;
use App\Services\PenugasanPeran;
use Database\Seeders\JenjangSeeder;
use Database\Seeders\PeranSeeder;
use Database\Seeders\TingkatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkademikPenilaianTest extends TestCase
{
    use RefreshDatabase;

    private ?Cabang $cabang = null;
    private ?Cabang $cabangLain = null;
    private ?Sekolah $sekolah = null;
    private ?Sekolah $sekolahLain = null;
    private ?Jenjang $jenjang = null;
    private ?TahunAjaran $tahunAjaran = null;
    private ?TahunAjaran $tahunAjaranLain = null;
    private ?Semester $semesterGanjil = null;
    private ?Semester $semesterGenap = null;
    private ?Tingkat $tingkat = null;
    private ?MataPelajaran $mataPelajaran = null;
    private ?MataPelajaran $mataPelajaranLain = null;
    private ?PenugasanMengajar $penugasan = null;
    private ?User $sysadmin = null;
    private ?User $kepalaCabang = null;
    private ?OlahNilaiService $olahNilai = null;
protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JenjangSeeder::class);
        $this->seed(PeranSeeder::class);
        $this->seed(TingkatSeeder::class);

        $this->jenjang = Jenjang::where('kode', 'SD')->first();
        $this->tingkat = Tingkat::where('jenjang_id', $this->jenjang->id)
            ->where('kode', '1')->first();

        $this->cabang = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);
        $this->cabangLain = Cabang::create(['kode' => 'FTN-02', 'nama' => 'Fathona 02']);

        $this->sekolah = Sekolah::create([
            'cabang_id' => $this->cabang->id,
            'jenjang_id' => $this->jenjang->id,
            'nama' => 'SD Fathona',
            'npsn' => '12345678',
            'status' => 'aktif',
        ]);

        $this->sekolahLain = Sekolah::create([
            'cabang_id' => $this->cabangLain->id,
            'jenjang_id' => $this->jenjang->id,
            'nama' => 'SD Fathona 02',
            'npsn' => '87654321',
            'status' => 'aktif',
        ]);

        $this->mataPelajaran = MataPelajaran::create([
            'jenjang_id' => $this->jenjang->id,
            'kode' => 'MTK',
            'nama' => 'Matematika',
        ]);

        $this->mataPelajaranLain = MataPelajaran::create([
            'jenjang_id' => $this->jenjang->id,
            'kode' => 'BIND',
            'nama' => 'Bahasa Indonesia',
        ]);

        $this->tahunAjaran = TahunAjaran::create([
            'kode' => '2026/2027',
            'nama' => 'Tahun Ajaran 2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'is_aktif' => true,
        ]);

        $this->tahunAjaranLain = TahunAjaran::create([
            'kode' => '2025/2026',
            'nama' => 'Tahun Ajaran 2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'is_aktif' => false,
        ]);

        $this->semesterGanjil = Semester::create([
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'nama' => 'Ganjil',
            'urutan' => 1,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-12-31',
            'is_aktif' => true,
        ]);

        $this->semesterGenap = Semester::create([
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'nama' => 'Genap',
            'urutan' => 2,
            'tanggal_mulai' => '2027-01-01',
            'tanggal_selesai' => '2027-06-30',
            'is_aktif' => false,
        ]);

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

        $this->olahNilai = new OlahNilaiService();
    }
public function test_nilai_remedial_termasuk_hitung(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $this->buatNilaiSiswa($penugasan, $siswa1, 70.00, 50.00, 'Remedial mengikuti');

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNotNull($nilaiAkhir);
    $this->assertEquals(60.00, $nilaiAkhir->nilai_akhir);
}

public function test_end_to_end_penghitungan_nilai_akhir(): void
{
    $this->actingAs($this->sysadmin);

    // 1. Buat penugasan
    $penugasan = $this->buatPenugasan();

    // 2. Buat beberapa siswa
    $siswa1 = $this->buatSiswaAktif(['nama_lengkap' => 'Siswa A']);
    $siswa2 = $this->buatSiswaAktif(['nama_lengkap' => 'Siswa B']);
    $siswa3 = $this->buatSiswaAktif(['nama_lengkap' => 'Siswa C']);

    // 3. Buat komponen penilaian dan input nilai
    $this->buatNilaiSiswa($penugasan, $siswa1, 80.00, null, 'Baik');
    $this->buatNilaiSiswa($penugasan, $siswa2, 90.00, null, 'Sangat Baik');
    $this->buatNilaiSiswa($penugasan, $siswa3, 70.00, 60.00, 'Remedial');

    // 4. Buat aturan olah nilai
    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'pembulatan' => 'standar',
        'skala_id' => null,
    ]);

    // 5. Hitung nilai akhir
    $result = $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);
    $this->assertGreaterThan(0, $result);

    // 6. Verifikasi nilai akhir untuk masing-masing siswa
    $nilai1 = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->where('semester_id', $this->semesterGanjil->id)
        ->first();

    $nilai2 = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa2->id)
        ->where('semester_id', $this->semesterGanjil->id)
        ->first();

    $nilai3 = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa3->id)
        ->where('semester_id', $this->semesterGanjil->id)
        ->first();

    $this->assertNotNull($nilai1);
    $this->assertNotNull($nilai2);
    $this->assertNotNull($nilai3);

    $this->assertEquals(80.00, $nilai1->nilai_akhir);
    $this->assertEquals(90.00, $nilai2->nilai_akhir);
    $this->assertEquals(65.00, $nilai3->nilai_akhir);

    // 7. Finalisasi
    $finalCount = $this->olahNilai->finalisasiNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);
    $this->assertGreaterThan(0, $finalCount);

    // 8. Verifikasi finalisasi
    $final1 = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->where('status', 'final')
        ->first();
    $this->assertNotNull($final1);
    $this->assertEquals('final', $final1->status);
}

public function test_hitung_nilai_akhir_semester_terjaga(): void
{
    $this->actingAs($this->sysadmin);
    $penugasanGanjil = $this->buatPenugasan($this->sekolah, $this->mataPelajaran, $this->tahunAjaran, $this->semesterGanjil);
    $penugasanGenap = $this->buatPenugasan($this->sekolah, $this->mataPelajaran, $this->tahunAjaran, $this->semesterGenap);

    $siswa1 = $this->buatSiswaAktif();
    $siswa2 = $this->buatSiswaAktif();

    $this->buatNilaiSiswa($penugasanGanjil, $siswa1, 80.00, null, null);
    $this->buatNilaiSiswa($penugasanGenap, $siswa2, 90.00, null, null);

    AturanOlahNilai::create([
        'sekolah_id' => $this->sekolah->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'mata_pelajaran_id' => $this->mataPelajaran->id,
        'tingkat_id' => $this->tingkat->id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $this->olahNilai->hitungNilaiAkhir($penugasanGanjil, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhirGanjil = NilaiAkhir::where('penugasan_mengajar_id', $penugasanGanjil->id)
        ->where('siswa_id', $siswa1->id)
        ->where('semester_id', $this->semesterGanjil->id)
        ->first();

    $this->assertNotNull($nilaiAkhirGanjil);
    $this->assertEquals('draft', $nilaiAkhirGanjil->status);

    $this->olahNilai->hitungNilaiAkhir($penugasanGenap, $this->semesterGenap, $this->sysadmin);

    $nilaiAkhirGenap = NilaiAkhir::where('penugasan_mengajar_id', $penugasanGenap->id)
        ->where('siswa_id', $siswa2->id)
        ->where('semester_id', $this->semesterGenap->id)
        ->first();

    $this->assertNotNull($nilaiAkhirGenap);
    $this->assertEquals('draft', $nilaiAkhirGenap->status);
}
public function test_isolasi_cabang_nilai_akhir(): void
{
    $this->actingAs($this->sysadmin);
    $penugasanSekolah1 = $this->buatPenugasan($this->sekolah);
    $penugasanSekolah2 = $this->buatPenugasan($this->sekolahLain, $this->mataPelajaranLain);

    $aturan1 = AturanOlahNilai::create([
        'sekolah_id' => $this->sekolah->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'mata_pelajaran_id' => $this->mataPelajaran->id,
        'tingkat_id' => $this->tingkat->id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $aturan2 = AturanOlahNilai::create([
        'sekolah_id' => $this->sekolahLain->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'mata_pelajaran_id' => $this->mataPelajaranLain->id,
        'tingkat_id' => $this->tingkat->id,
        'metode' => 'rata_berbobot',
        'kkm' => 80.00,
        'skala_id' => null,
    ]);

    KomponenPenilaian::create([
        'sekolah_id' => $this->sekolahLain->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'kode' => 'UTS',
        'nama' => 'Ujian Tengah Semester',
        'jenis' => 'uts',
        'bobot' => 1.00,
    ]);

    $siswa1 = $this->buatSiswaAktif(['nama_lengkap' => 'Siswa Sekolah 1']);
    $siswa2 = $this->buatSiswaAktif(['nama_lengkap' => 'Siswa Sekolah 2'], $this->sekolahLain);

    $this->buatNilaiSiswa($penugasanSekolah1, $siswa1, 85.00, null, null);

    $this->olahNilai->hitungNilaiAkhir($penugasanSekolah1, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir1 = NilaiAkhir::where('penugasan_mengajar_id', $penugasanSekolah1->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNotNull($nilaiAkhir1);
    $this->assertEquals(85.00, $nilaiAkhir1->nilai_akhir);

    $this->buatNilaiSiswa($penugasanSekolah2, $siswa2, 90.00, null, null);

    $this->olahNilai->hitungNilaiAkhir($penugasanSekolah2, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir2 = NilaiAkhir::where('penugasan_mengajar_id', $penugasanSekolah2->id)
        ->where('siswa_id', $siswa2->id)
        ->first();

    $this->assertNotNull($nilaiAkhir2);
    $this->assertEquals(90.00, $nilaiAkhir2->nilai_akhir);
}
public function test_kkm_dan_skala_butir_dihitung(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $this->buatNilaiSiswa($penugasan, $siswa1, 80.00, null, null);

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 75.00,
        'skala_id' => null,
    ]);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNotNull($nilaiAkhir);
    $this->assertEquals(75.00, $nilaiAkhir->kkm);
    $this->assertTrue($nilaiAkhir->is_tuntas ?? false);
}

public function test_kkm_di_bawah_nilai_tidak_tuntas(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $this->buatNilaiSiswa($penugasan, $siswa1, 60.00, null, null);

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 80.00,
        'skala_id' => null,
    ]);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNotNull($nilaiAkhir);
    $this->assertEquals(80.00, $nilaiAkhir->kkm);
    $this->assertFalse($nilaiAkhir->is_tuntas);
}

public function test_pembulatan_standar(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'pembulatan' => 'standar',
        'skala_id' => null,
    ]);

    $this->buatNilaiSiswa($penugasan, $siswa1, 82.35, null, null);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNotNull($nilaiAkhir);
}

public function test_pembulatan_atas(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'pembulatan' => 'atas',
        'skala_id' => null,
    ]);

    $this->buatNilaiSiswa($penugasan, $siswa1, 82.35, null, null);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNotNull($nilaiAkhir);
}

public function test_pembulatan_bawah(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'pembulatan' => 'bawah',
        'skala_id' => null,
    ]);

    $this->buatNilaiSiswa($penugasan, $siswa1, 82.35, null, null);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNotNull($nilaiAkhir);
}
public function test_finalisasi_nilai_akhir_draft_ke_final(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $this->buatNilaiSiswa($penugasan, $siswa1, 80.00, null, null);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->where('status', 'draft')
        ->first();

    $this->assertNotNull($nilaiAkhir);

    $finalCount = $this->olahNilai->finalisasiNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $this->assertGreaterThan(0, $finalCount);

    $finalCheck = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->where('status', 'final')
        ->first();

    $this->assertNotNull($finalCheck);
    $this->assertEquals('final', $finalCheck->status);
}

public function test_finalisasi_nilai_akhir_sudah_final_ditolak(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $this->buatNilaiSiswa($penugasan, $siswa1, 80.00, null, null);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);
    $this->olahNilai->finalisasiNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $this->expectException(OlahNilaiException::class);
    $this->olahNilai->finalisasiNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);
}

public function test_koreksi_nilai_akhir_draft(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $this->buatNilaiSiswa($penugasan, $siswa1, 80.00, null, null);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->where('status', 'draft')
        ->first();

    $this->assertNotNull($nilaiAkhir);

    $dikoreksi = $this->olahNilai->koreksiNilaiAkhir(
        nilaiAkhir: $nilaiAkhir,
        nilaiBaru: 88.50,
        deskripsiBaru: 'Nilai diperbaiki',
        isTuntas: null,
        diperbaruiOleh: $this->sysadmin,
    );

    $this->assertEquals(88.50, $dikoreksi->nilai_akhir);
    $this->assertEquals('Nilai diperbaiki', $dikoreksi->deskripsi);

    $verifikasi = NilaiAkhir::where('id', $nilaiAkhir->id)->first();
    $this->assertEquals(88.50, $verifikasi->nilai_akhir);
    $this->assertEquals('Nilai diperbaiki', $verifikasi->deskripsi);
}

public function test_koreksi_nilai_akhir_final_ditolak(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $this->buatNilaiSiswa($penugasan, $siswa1, 80.00, null, null);

    $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);
    $this->olahNilai->finalisasiNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $this->expectException(OlahNilaiException::class);
    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->where('status', 'final')
        ->first();

    $this->olahNilai->koreksiNilaiAkhir(
        nilaiAkhir: $nilaiAkhir,
        nilaiBaru: 99.00,
        deskripsiBaru: null,
        isTuntas: null,
        diperbaruiOleh: $this->sysadmin,
    );
}
public function test_hitung_nilai_akhir_rata_sumatif(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();
    $siswa2 = $this->buatSiswaAktif();

    $this->buatNilaiSiswa($penugasan, $siswa1, 80.00, null, null);
    $this->buatNilaiSiswa($penugasan, $siswa2, 70.00, null, null);

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_sumatif',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $result = $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);
    $this->assertGreaterThan(0, $result);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNotNull($nilaiAkhir);
}

public function test_hitung_nilai_akhir_formula(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $this->buatNilaiSiswa($penugasan, $siswa1, 85.00, null, null);

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'formula',
        'formula' => [0.3, 0.7],
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $result = $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);
    $this->assertGreaterThan(0, $result);
}

public function test_hitung_nilai_akhir_tanpa_data(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $result = $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);
    $this->assertGreaterThanOrEqual(0, $result);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNull($nilaiAkhir);
}
public function test_hitung_nilai_akhir_nilai_tertinggi(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();
    $siswa2 = $this->buatSiswaAktif();

    $this->buatNilaiSiswa($penugasan, $siswa1, 75.00, null, null);
    $this->buatNilaiSiswa($penugasan, $siswa2, 95.00, null, null);

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'nilai_tertinggi',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $result = $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);
    $this->assertGreaterThan(0, $result);

    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa2->id)
        ->first();

    $this->assertNotNull($nilaiAkhir);
    $this->assertEquals(95.00, $nilaiAkhir->nilai_akhir);
}
public function test_hitung_nilai_akhir_rata_berbobot(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa1 = $this->buatSiswaAktif();
    $siswa2 = $this->buatSiswaAktif();

    $this->buatNilaiSiswa($penugasan, $siswa1, 80.00, null, 'Baik');
    $this->buatNilaiSiswa($penugasan, $siswa2, 90.00, null, 'Sangat Baik');

    $aturan = AturanOlahNilai::create([
        'sekolah_id' => $penugasan->sekolah_id,
        'tahun_ajaran_id' => $penugasan->tahun_ajaran_id,
        'mata_pelajaran_id' => $penugasan->mata_pelajaran_id,
        'tingkat_id' => $penugasan->rombel->tingkat_id,
        'metode' => 'rata_berbobot',
        'kkm' => 70.00,
        'skala_id' => null,
    ]);

    $result = $this->olahNilai->hitungNilaiAkhir($penugasan, $this->semesterGanjil, $this->sysadmin);

    $this->assertGreaterThan(0, $result);
    $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
        ->where('siswa_id', $siswa1->id)
        ->first();

    $this->assertNotNull($nilaiAkhir);
    $this->assertEquals('draft', $nilaiAkhir->status);
}
public function test_nilai_siswa_tidak_bisa_diinput_setelah_final(): void
{
    $this->expectException(PenilaianException::class);
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    app(PenilaianService::class)->finalisasiPenilaian(
        Penilaian::where('penugasan_mengajar_id', $penugasan->id)
            ->where('status', 'draft')
            ->firstOrFail(),
        $this->sysadmin,
    );

    $penilaian = Penilaian::where('penugasan_mengajar_id', $penugasan->id)
        ->where('status', 'final')
        ->firstOrFail();

    $siswa = $this->buatSiswaAktif();

    app(PenilaianService::class)->inputNilai(
        penilaian: $penilaian,
        siswa: $siswa,
        nilai: 70.00,
        nilaiRemedial: null,
        catatan: null,
        dicatatOleh: $this->sysadmin,
    );
}
public function test_nilai_siswa_bisa_diinput(): void
{
    $this->actingAs($this->sysadmin);
    $penugasan = $this->buatPenugasan();

    $siswa = $this->buatSiswaAktif();

    $nilaiSiswa = app(PenilaianService::class)->inputNilai(
        penilaian: Penilaian::where('penugasan_mengajar_id', $penugasan->id)
            ->where('status', 'draft')
            ->firstOrFail(),
        siswa: $siswa,
        nilai: 85.50,
        nilaiRemedial: 70.00,
        catatan: 'Nilai baik',
        dicatatOleh: $this->sysadmin,
    );

    $this->assertNotNull($nilaiSiswa->id);
    $this->assertEquals(85.50, $nilaiSiswa->nilai);
    $this->assertEquals(70.00, $nilaiSiswa->nilai_remedial);
    $this->assertEquals('Nilai baik', $nilaiSiswa->catatan);
}

    // --- Helper methods ---

    private function buatPenugasan($sekolah = null, $mataPelajaran = null, $tahunAjaran = null, $semester = null): PenugasanMengajar
    {
        $sekolah = $sekolah ?? $this->sekolah;
        $mataPelajaran = $mataPelajaran ?? $this->mataPelajaran;
        $tahunAjaran = $tahunAjaran ?? $this->tahunAjaran;
        $semester = $semester ?? $this->semesterGanjil;

        // Buat rombel untuk penugasan (terkait dengan tingkat)
        $rombel = \App\Models\Rombel::firstOrCreate([
            'sekolah_id' => $sekolah->id,
            'tingkat_id' => $this->tingkat->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'cabang_id' => $sekolah->cabang_id,
            'nama' => 'Rombel Test ' . $this->tingkat->kode,
            'status' => 'aktif',
        ]);

        // Pastikan komponen penilaian ada untuk penugasan ini
        $komponen = KomponenPenilaian::firstOrCreate([
            'sekolah_id' => $sekolah->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kode' => 'UTS',
            'nama' => 'Ujian Tengah Semester',
            'jenis' => 'uts',
        ]);

        $penugasan = PenugasanMengajar::firstOrCreate(
            [
                'sekolah_id' => $sekolah->id,
                'mata_pelajaran_id' => $mataPelajaran->id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'semester_id' => $semester->id,
                'rombel_id' => $rombel->id,
                'pegawai_id' => null,
            ],
            [
                'peran' => 'utama',
                'jam_per_minggu' => 4,
                'no_sk' => 'SK/FTN/' . $tahunAjaran->kode . '/001',
                'status' => 'aktif',
            ]
        );

        // Pastikan penilaian draft ada untuk komponen ini
        Penilaian::firstOrCreate([
            'penugasan_mengajar_id' => $penugasan->id,
            'semester_id' => $semester->id,
            'komponen_id' => $komponen->id,
            'judul' => 'Nilai UTS',
            'tanggal' => now()->format('Y-m-d'),
            'nilai_maks' => 100.00,
            'status' => 'draft',
            'sumber' => 'manual',
        ]);

        return $penugasan;
    }

    private function buatSiswaAktif(array $attributes = [], ?Sekolah $sekolah = null): Siswa
    {
        $sekolah = $sekolah ?? $this->sekolah;
        $namaLengkap = $attributes['nama_lengkap'] ?? 'Siswa Test';
        unset($attributes['nama_lengkap']);

        $orang = Orang::create([
            'nama_lengkap' => $namaLengkap,
            'jenis_kelamin' => 'L',
        ]);

        $siswa = Siswa::create(array_merge([
            'orang_id' => $orang->id,
            'nis_yayasan' => 'SISWA' . str_pad($orang->id, 4, '0', STR_PAD_LEFT),
            'tanggal_masuk_yayasan' => now()->format('Y-m-d'),
            'status' => 'aktif',
        ], $attributes));

        // Buat RombelSiswa untuk asosiasi siswa ke rombel
        $rombel = Rombel::firstOrCreate([
            'sekolah_id' => $sekolah->id,
            'tingkat_id' => $this->tingkat->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'cabang_id' => $sekolah->cabang_id,
            'nama' => 'Rombel Test ' . $this->tingkat->kode,
            'status' => 'aktif',
        ]);

        RombelSiswa::firstOrCreate([
            'rombel_id' => $rombel->id,
            'siswa_id' => $siswa->id,
            'cabang_id' => $sekolah->cabang_id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'tanggal_masuk' => now()->format('Y-m-d'),
            'status' => 'aktif',
            'tanggal_keluar' => null,
        ]);

        return $siswa;
    }

    private function buatNilaiSiswa(PenugasanMengajar $penugasan, Siswa $siswa, ?float $nilai = null, ?float $nilaiRemedial = null, ?string $catatan = null): void
    {
        $komponen = KomponenPenilaian::where('sekolah_id', $penugasan->sekolah_id)
            ->where('tahun_ajaran_id', $penugasan->tahun_ajaran_id)
            ->first();

        if (!$komponen) {
            return;
        }

        $penilaian = Penilaian::where('penugasan_mengajar_id', $penugasan->id)
            ->where('semester_id', $penugasan->semester_id)
            ->where('komponen_id', $komponen->id)
            ->first();

        if (!$penilaian) {
            $penilaian = Penilaian::create([
                'penugasan_mengajar_id' => $penugasan->id,
                'semester_id' => $penugasan->semester_id,
                'komponen_id' => $komponen->id,
                'judul' => 'Nilai UTS',
                'tanggal' => now()->format('Y-m-d'),
                'nilai_maks' => 100.00,
                'status' => 'draft',
                'sumber' => 'manual',
            ]);
        }

        NilaiSiswa::updateOrCreate(
            ['penilaian_id' => $penilaian->id, 'siswa_id' => $siswa->id],
            [
                'nilai' => $nilai ?? rand(50, 100),
                'nilai_remedial' => $nilaiRemedial ?? null,
                'catatan' => $catatan,
            ]
        );
    }
}