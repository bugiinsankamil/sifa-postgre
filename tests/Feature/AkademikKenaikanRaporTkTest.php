<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Exceptions\Akademik\KenaikanKelasException;
use App\Exceptions\Akademik\PenilaianTkException;
use App\Exceptions\Akademik\RaporException;
use App\Models\Cabang;
use App\Models\Jenjang;
use App\Models\KeputusanKenaikan;
use App\Models\Rombel;
use App\Models\RombelSiswa;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Services\Akademik\KenaikanKelasService;
use App\Services\Akademik\PenilaianTkService;
use App\Services\Akademik\RaporService;
use App\Services\PenugasanPeran;
use Database\Seeders\JenjangSeeder;
use Database\Seeders\PeranSeeder;
use Database\Seeders\TingkatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkademikKenaikanRaporTkTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;
    private Cabang $cabangLain;
    private \App\Models\Sekolah $sekolah;
    private Jenjang $jenjang;
    private TahunAjaran $tahunAjaran;
    private TahunAjaran $tahunAjaranBerikutnya;
    private Semester $semester;
    private Tingkat $tingkat;
    private Rombel $rombel;
    private Rombel $rombelTujuan;
    private Siswa $siswa;
    private User $sysadmin;
    private User $kepalaCabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JenjangSeeder::class);
        $this->seed(TingkatSeeder::class);
        $this->seed(PeranSeeder::class);

        $this->jenjang = Jenjang::where('kode', 'SD')->first();
        $this->tingkat = Tingkat::where('jenjang_id', $this->jenjang->id)
            ->where('kode', '1')->first();

        $this->cabang = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);
        $this->cabangLain = Cabang::create(['kode' => 'FTN-02', 'nama' => 'Fathona 02']);

        $this->sekolah = \App\Models\Sekolah::create([
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

        $this->tahunAjaranBerikutnya = TahunAjaran::create([
            'kode' => '2027/2028',
            'nama' => 'Tahun Ajaran 2027/2028',
            'tanggal_mulai' => '2027-07-01',
            'tanggal_selesai' => '2028-06-30',
            'is_aktif' => false,
        ]);

        $this->semester = Semester::create([
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'nama' => 'Ganjil',
            'urutan' => 1,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-12-31',
            'is_aktif' => true,
        ]);

        $this->rombel = Rombel::create([
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->cabang->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'tingkat_id' => $this->tingkat->id,
            'nama' => '1A',
            'status' => 'aktif',
        ]);

        $this->rombelTujuan = Rombel::create([
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->cabang->id,
            'tahun_ajaran_id' => $this->tahunAjaranBerikutnya->id,
            'tingkat_id' => $this->tingkat->id,
            'nama' => '2A',
            'status' => 'aktif',
        ]);

        $orangSiswa = \App\Models\Orang::create(['nama_lengkap' => 'Siswa SD', 'jenis_kelamin' => 'L']);
        $this->siswa = Siswa::create([
            'orang_id' => $orangSiswa->id,
            'nis_yayasan' => '20260001',
            'tanggal_masuk_yayasan' => '2026-07-01',
            'status' => 'aktif',
        ]);
        RombelSiswa::create([
            'rombel_id' => $this->rombel->id,
            'siswa_id' => $this->siswa->id,
            'cabang_id' => $this->cabang->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'no_absen' => 1,
            'tanggal_masuk' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $orangSysadmin = \App\Models\Orang::create(['nama_lengkap' => 'Sysadmin', 'jenis_kelamin' => 'L']);
        $this->sysadmin = User::create([
            'orang_id' => $orangSysadmin->id,
            'username' => 'sysadmin_kr',
            'password' => 'pw',
        ]);
        app(PenugasanPeran::class)->berikan($this->sysadmin, Peran::Sysadmin);

        $orangKepala = \App\Models\Orang::create(['nama_lengkap' => 'Kepala Cabang', 'jenis_kelamin' => 'L']);
        $this->kepalaCabang = User::create([
            'orang_id' => $orangKepala->id,
            'username' => 'kepala_kr',
            'password' => 'pw',
        ]);
        app(PenugasanPeran::class)->berikan($this->kepalaCabang, Peran::KepalaCabang, $this->cabang->id);
    }

    public function test_tetapkan_keputusan_naik_idemen(): void
    {
        $this->actingAs($this->sysadmin);
        $service = new KenaikanKelasService();

        $keputusan = $service->tetapkanKeputusan(
            $this->siswa,
            $this->rombel,
            $this->tahunAjaran,
            'naik',
            $this->rombelTujuan,
            'Naik ke kelas 2',
            $this->sysadmin
        );

        $this->assertSame('naik', $keputusan->keputusan);
        $this->assertSame($this->rombelTujuan->id, $keputusan->rombel_tujuan_id);

        $keputusan2 = $service->tetapkanKeputusan(
            $this->siswa,
            $this->rombel,
            $this->tahunAjaran,
            'lulus',
            null,
            'Lulus',
            $this->sysadmin
        );
        $this->assertSame($keputusan->id, $keputusan2->id);
        $this->assertSame('naik', $keputusan2->keputusan);
    }

    public function test_tetapkan_keputusan_keputusan_tidak_valid_ditolak(): void
    {
        $this->expectException(KenaikanKelasException::class);
        $this->actingAs($this->sysadmin);
        $service = new KenaikanKelasService();

        $service->tetapkanKeputusan(
            $this->siswa,
            $this->rombel,
            $this->tahunAjaran,
            'invalid',
            null,
            null,
            $this->sysadmin
        );
    }

    public function test_ubah_keputusan_berhasil(): void
    {
        $this->actingAs($this->sysadmin);
        $service = new KenaikanKelasService();

        $keputusan = $service->tetapkanKeputusan(
            $this->siswa,
            $this->rombel,
            $this->tahunAjaran,
            'naik',
            $this->rombelTujuan,
            'Naik',
            $this->sysadmin
        );

        $diubah = $service->ubahKeputusan(
            $keputusan,
            'lulus',
            null,
            'Lulus naik',
            $this->sysadmin
        );
        $this->assertSame('lulus', $diubah->keputusan);
        $this->assertSame('Lulus naik', $diubah->catatan);
    }

    public function test_bentuk_rombel_tahun_berikutnya_hanya_naik(): void
    {
        $this->expectException(KenaikanKelasException::class);
        $this->actingAs($this->sysadmin);
        $service = new KenaikanKelasService();

        $keputusan = $service->tetapkanKeputusan(
            $this->siswa,
            $this->rombel,
            $this->tahunAjaran,
            'lulus',
            null,
            'Lulus',
            $this->sysadmin
        );

        $service->bentukRombelTahunBerikutnya(
            $keputusan,
            $this->rombelTujuan,
            $this->tahunAjaranBerikutnya,
            $this->sysadmin
        );
    }

    public function test_bentuk_rombel_tahun_berikutnya_idemen(): void
    {
        $this->actingAs($this->sysadmin);
        $service = new KenaikanKelasService();

        $keputusan = $service->tetapkanKeputusan(
            $this->siswa,
            $this->rombel,
            $this->tahunAjaran,
            'naik',
            $this->rombelTujuan,
            'Naik',
            $this->sysadmin
        );

        $service->bentukRombelTahunBerikutnya(
            $keputusan,
            $this->rombelTujuan,
            $this->tahunAjaranBerikutnya,
            $this->sysadmin
        );

        $this->assertDatabaseHas('rombel_siswa', [
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelTujuan->id,
            'tahun_ajaran_id' => $this->tahunAjaranBerikutnya->id,
            'status' => 'aktif',
        ]);

        $this->assertDatabaseHas('siswa_sekolah', [
            'siswa_id' => $this->siswa->id,
            'tahun_ajaran_id' => $this->tahunAjaranBerikutnya->id,
            'status' => 'aktif',
        ]);

        $sebelum = RombelSiswa::where('siswa_id', $this->siswa->id)
            ->where('rombel_id', $this->rombelTujuan->id)
            ->where('tahun_ajaran_id', $this->tahunAjaranBerikutnya->id)
            ->count();

        $service->bentukRombelTahunBerikutnya(
            $keputusan,
            $this->rombelTujuan,
            $this->tahunAjaranBerikutnya,
            $this->sysadmin
        );

        $sesudah = RombelSiswa::where('siswa_id', $this->siswa->id)
            ->where('rombel_id', $this->rombelTujuan->id)
            ->where('tahun_ajaran_id', $this->tahunAjaranBerikutnya->id)
            ->count();

        $this->assertSame($sebelum, $sesudah);
    }

    public function test_isolasi_cabang_kenaikan_kelas(): void
    {
        $this->actingAs($this->sysadmin);
        $service = new KenaikanKelasService();

        $sekolahLain = \App\Models\Sekolah::create([
            'cabang_id' => $this->cabangLain->id,
            'jenjang_id' => $this->jenjang->id,
            'nama' => 'SD Fathona 02',
            'npsn' => '87654322',
            'status' => 'aktif',
        ]);
        $rombelLain = Rombel::create([
            'sekolah_id' => $sekolahLain->id,
            'cabang_id' => $this->cabangLain->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'tingkat_id' => $this->tingkat->id,
            'nama' => '1B',
            'status' => 'aktif',
        ]);
        $siswaLain = Siswa::create([
            'orang_id' => \App\Models\Orang::create(['nama_lengkap' => 'Siswa Cabang 2', 'jenis_kelamin' => 'L'])->id,
            'nis_yayasan' => '20260003',
            'tanggal_masuk_yayasan' => '2026-07-01',
            'status' => 'aktif',
        ]);

        app(\App\Support\CabangKonteks::class)->tetapkan([$this->cabang->id]);

        $this->expectException(KenaikanKelasException::class);
        $service->tetapkanKeputusan(
            $siswaLain,
            $rombelLain,
            $this->tahunAjaran,
            'naik',
            null,
            null,
            $this->sysadmin
        );
    }

    public function test_buat_draft_rapor_idemen(): void
    {
        $this->actingAs($this->sysadmin);
        $service = new RaporService();

        $rapor = $service->buatDraft(
            $this->siswa,
            $this->semester,
            null,
            $this->sysadmin
        );

        $this->assertSame('draft', $rapor->status);
        $this->assertSame($this->siswa->id, $rapor->siswa_id);
        $this->assertSame($this->semester->id, $rapor->semester_id);

        $rapor2 = $service->buatDraft(
            $this->siswa,
            $this->semester,
            null,
            $this->sysadmin
        );
        $this->assertSame($rapor->id, $rapor2->id);
    }

    public function test_verifikasi_dan_terbitkan_rapor(): void
    {
        $this->actingAs($this->sysadmin);
        $service = new RaporService();

        $rapor = $service->buatDraft($this->siswa, $this->semester, null, $this->sysadmin);

        $terverifikasi = $service->verifikasi($rapor, $this->sysadmin);
        $this->assertSame('diverifikasi', $terverifikasi->status);

        $ditandatangani = User::create([
            'orang_id' => \App\Models\Orang::create(['nama_lengkap' => 'Wali Kelas', 'jenis_kelamin' => 'L'])->id,
            'username' => 'walikelas',
            'password' => 'pw',
        ]);

        $terbit = $service->terbitkan($rapor, $ditandatangani);
        $this->assertSame('terbit', $terbit->status);
        $this->assertNotNull($terbit->tanggal_terbit);
        $this->assertSame($ditandatangani->id, $terbit->ditandatangani_oleh);
    }

    public function test_rapor_sudah_terbit_tidak_dapat_diubah(): void
    {
        $this->expectException(RaporException::class);
        $this->actingAs($this->sysadmin);
        $service = new RaporService();

        $rapor = $service->buatDraft($this->siswa, $this->semester, null, $this->sysadmin);
        $service->terbitkan($rapor, $this->sysadmin);

        $service->terbitkan($rapor, $this->sysadmin);
    }

    public function test_rapor_belum_punya_butir_tidak_dapat_terbit(): void
    {
        $this->expectException(RaporException::class);
        $this->actingAs($this->sysadmin);
        $service = new RaporService();

        $rapor = $service->buatDraft($this->siswa, $this->semester, null, $this->sysadmin);
        $service->verifikasi($rapor, $this->sysadmin);
        $service->terbitkan($rapor, $this->sysadmin);
    }

    public function test_catat_observasi_dan_ceklis_tk(): void
    {
        $this->actingAs($this->sysadmin);
        $service = new PenilaianTkService();

        $orangAspek = \App\Models\Orang::create(['nama_lengkap' => 'Guru TK', 'jenis_kelamin' => 'L']);
        $pegawai = \App\Models\Pegawai::create([
            'orang_id' => $orangAspek->id,
            'nip_yayasan' => '198001010002',
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'pns',
            'tanggal_masuk' => '2020-01-01',
            'status' => 'aktif',
        ]);

        $aspek = \App\Models\TkAspek::create([
            'kurikulum_id' => null,
            'kode' => 'SOSIAL',
            'nama' => 'Sosial Emosional',
            'urutan' => 1,
            'is_aktif' => true,
        ]);

        $tingkatTk = \App\Models\Tingkat::create([
            'jenjang_id' => \App\Models\Jenjang::where('kode', 'TK')->first()?->id ?? $this->jenjang->id,
            'kode' => 'TK-A',
            'nama' => 'TK A',
        ]);

        $indikator = \App\Models\TkIndikator::create([
            'aspek_id' => $aspek->id,
            'tingkat_id' => $tingkatTk->id,
            'kode' => 'SOS-01',
            'uraian' => 'Bisa berinteraksi dengan teman',
            'urutan' => 1,
            'is_aktif' => true,
        ]);

        $observasi = $service->catatObservasi(
            $this->siswa,
            $this->semester,
            '2026-09-01',
            'Siswa bersemangat berinteraksi.',
            'Bermain bersama teman',
            null,
            null,
            null,
            $aspek->id,
            $this->sysadmin
        );

        $this->assertNotNull($observasi->id);
        $this->assertSame('Siswa bersemangat berinteraksi.', $observasi->uraian);

        $ceklis = $service->catatCeklisCapaian(
            $this->siswa,
            $this->semester,
            $indikator->id,
            '2026-09-01',
            null,
            'Berkembang baik',
            $observasi->id,
            $this->sysadmin
        );

        $this->assertNotNull($ceklis->id);
        $this->assertSame('Berkembang baik', $ceklis->catatan);
    }

    public function test_susun_dan_finalisasi_asesmen_tk(): void
    {
        $this->actingAs($this->sysadmin);
        $service = new PenilaianTkService();

        $orangAspek = \App\Models\Orang::create(['nama_lengkap' => 'Guru TK', 'jenis_kelamin' => 'L']);
        $pegawai = \App\Models\Pegawai::create([
            'orang_id' => $orangAspek->id,
            'nip_yayasan' => '198001010003',
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'pns',
            'tanggal_masuk' => '2020-01-01',
            'status' => 'aktif',
        ]);

        $aspek = \App\Models\TkAspek::create([
            'kurikulum_id' => null,
            'kode' => 'KOGNITIF',
            'nama' => 'Kognitif',
            'urutan' => 1,
            'is_aktif' => true,
        ]);

        $asesmen = $service->susunAsesmenSemester(
            $this->siswa,
            $this->semester,
            $aspek,
            'Siswa menunjukkan perkembangan kognitif yang baik.',
            null,
            $this->sysadmin
        );

        $this->assertSame('draft', $asesmen->status);

        $final = $service->finalisasiAsesmen($asesmen, $this->sysadmin);
        $this->assertSame('final', $final->status);

        $this->expectException(PenilaianTkException::class);
        $service->finalisasiAsesmen($asesmen, $this->sysadmin);
    }

    public function test_isolasi_cabang_penilaian_tk(): void
    {
        $this->expectException(PenilaianTkException::class);
        $this->actingAs($this->sysadmin);
        $service = new PenilaianTkService();

        $siswaLain = Siswa::create([
            'orang_id' => \App\Models\Orang::create(['nama_lengkap' => 'Siswa Cabang 2 TK', 'jenis_kelamin' => 'L'])->id,
            'nis_yayasan' => '20260004',
            'tanggal_masuk_yayasan' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $rombelLain = Rombel::create([
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->cabangLain->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'tingkat_id' => $this->tingkat->id,
            'nama' => '1B',
            'status' => 'aktif',
        ]);
        RombelSiswa::create([
            'rombel_id' => $rombelLain->id,
            'siswa_id' => $siswaLain->id,
            'cabang_id' => $this->cabangLain->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'no_absen' => 1,
            'tanggal_masuk' => '2026-07-01',
            'status' => 'aktif',
        ]);

        app(\App\Support\CabangKonteks::class)->tetapkan([$this->cabang->id]);

        $service->catatObservasi(
            $siswaLain,
            $this->semester,
            '2026-09-01',
            'Observasi siswa cabang lain.',
            null,
            null,
            null,
            null,
            null,
            $this->sysadmin
        );
    }
}