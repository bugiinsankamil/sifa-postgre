<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Exceptions\Akademik\IzinSiswaException;
use App\Exceptions\Akademik\JurnalMengajarException;
use App\Exceptions\Akademik\PresensiSiswaException;
use App\Models\Cabang;
use App\Models\Jenjang;
use App\Models\JurnalMengajar;
use App\Models\MataPelajaran;
use App\Models\Orang;
use App\Models\PenugasanMengajar;
use App\Models\Pegawai;
use App\Models\PresensiSiswa;
use App\Models\Rombel;
use App\Models\RombelSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Akademik\IzinSiswaService;
use App\Services\Akademik\JurnalMengajarService;
use App\Services\Akademik\PresensiSiswaService;
use App\Services\PenugasanPeran;
use Database\Seeders\JenjangSeeder;
use Database\Seeders\PeranSeeder;
use Database\Seeders\TingkatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkademikPresensiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;
    private Cabang $cabangLain;
    private \App\Models\Sekolah $sekolah;
    private Jenjang $jenjang;
    private TahunAjaran $tahunAjaran;
    private \App\Models\Semester $semester;
    private \App\Models\Tingkat $tingkat;
    private Rombel $rombel;
    private MataPelajaran $mataPelajaran;
    private Pegawai $pegawai;
    private Siswa $siswa;
    private User $sysadmin;
    private User $kepalaCabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JenjangSeeder::class);
        $this->seed(TingkatSeeder::class);
        $this->seed(PeranSeeder::class);

        $this->cabang = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);
        $this->cabangLain = Cabang::create(['kode' => 'FTN-02', 'nama' => 'Fathona 02']);

        $this->jenjang = Jenjang::where('kode', 'SD')->first();
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
        $this->semester = \App\Models\Semester::create([
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'nama' => 'Ganjil',
            'urutan' => 1,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-12-31',
            'is_aktif' => true,
        ]);
        $this->tingkat = \App\Models\Tingkat::where('jenjang_id', $this->jenjang->id)
            ->where('kode', '1')->first();

        $this->rombel = Rombel::create([
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->cabang->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'tingkat_id' => $this->tingkat->id,
            'nama' => '1A',
            'status' => 'aktif',
        ]);

        $this->mataPelajaran = MataPelajaran::create([
            'kode' => 'PKN',
            'nama' => 'Pancasila',
            'kelompok_ref' => 'umum',
            'jenjang_id' => $this->jenjang->id,
            'is_aktif' => true,
        ]);

        $orangGuru = Orang::create(['nama_lengkap' => 'Guru SD', 'jenis_kelamin' => 'L']);
        $this->pegawai = Pegawai::create([
            'orang_id' => $orangGuru->id,
            'nip_yayasan' => '198001010001',
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'pns',
            'tanggal_masuk' => '2020-01-01',
            'status' => 'aktif',
        ]);

        $orangSiswa = Orang::create(['nama_lengkap' => 'Siswa SD', 'jenis_kelamin' => 'L']);
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

        $orangSysadmin = Orang::create(['nama_lengkap' => 'Sysadmin', 'jenis_kelamin' => 'L']);
        $this->sysadmin = User::create([
            'orang_id' => $orangSysadmin->id,
            'username' => 'sysadmin_presensi',
            'password' => 'pw',
        ]);
        app(PenugasanPeran::class)->berikan($this->sysadmin, Peran::Sysadmin);

        $orangKepala = Orang::create(['nama_lengkap' => 'Kepala Cabang', 'jenis_kelamin' => 'L']);
        $this->kepalaCabang = User::create([
            'orang_id' => $orangKepala->id,
            'username' => 'kepala_presensi',
            'password' => 'pw',
        ]);
        app(PenugasanPeran::class)->berikan($this->kepalaCabang, Peran::KepalaCabang, $this->cabang->id);
    }

    private function buatPenugasan(): PenugasanMengajar
    {
        return PenugasanMengajar::create([
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->cabang->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel->id,
            'mata_pelajaran_id' => $this->mataPelajaran->id,
            'pegawai_id' => $this->pegawai->id,
            'peran' => 'utama',
            'jam_per_minggu' => 2,
            'no_sk' => 'SK-001',
            'status' => 'aktif',
        ]);
    }

    public function test_sysadmin_bisa_buat_jurnal_harian_tk_sd(): void
    {
        $this->actingAs($this->sysadmin);
        $penugasan = $this->buatPenugasan();

        $jurnal = app(JurnalMengajarService::class)->buatJurnal(
            penugasan: $penugasan,
            tanggal: '2026-09-01',
            jamPelajaran: null,
            pengajar: null,
            materi: 'Pengenalan diri',
            status: 'hadir',
            pengguna: $this->sysadmin,
        );

        $this->assertDatabaseHas('jurnal_mengajar', [
            'id' => $jurnal->id,
            'penugasan_mengajar_id' => $penugasan->id,
            'tanggal' => '2026-09-01',
            'jam_pelajaran_id' => null,
            'status' => 'hadir',
        ]);
    }

    public function test_sysadmin_bisa_buat_jurnal_per_jam_smp(): void
    {
        $this->actingAs($this->sysadmin);
        $penugasan = $this->buatPenugasan();
        $jam = \App\Models\JamPelajaran::create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'cabang_id' => $this->cabang->id,
            'hari' => 'senin',
            'urutan' => 1,
            'jam_mulai' => '07:30:00',
            'jam_selesai' => '08:15:00',
            'tipe' => 'belajar',
            'nama' => 'PKN Jam 1',
            'status' => 'aktif',
        ]);

        $jurnal = app(JurnalMengajarService::class)->buatJurnal(
            penugasan: $penugasan,
            tanggal: '2026-09-01',
            jamPelajaran: $jam,
            pengajar: $this->pegawai,
            materi: 'PKN',
            status: 'hadir',
            pengguna: $this->sysadmin,
        );

        $this->assertNotNull($jurnal->jam_pelajaran_id);
        $this->assertDatabaseHas('jurnal_mengajar', [
            'id' => $jurnal->id,
            'jam_pelajaran_id' => $jam->id,
        ]);
    }
    public function test_jurnal_duplikat_penugasan_tanggal_jam_ditolak(): void
    {
        $this->expectException(JurnalMengajarException::class);
        $this->actingAs($this->sysadmin);
        $penugasan = $this->buatPenugasan();

        app(JurnalMengajarService::class)->buatJurnal(
            penugasan: $penugasan,
            tanggal: '2026-09-01',
            jamPelajaran: null,
            materi: 'Pertama',
            pengguna: $this->sysadmin,
        );

        app(JurnalMengajarService::class)->buatJurnal(
            penugasan: $penugasan,
            tanggal: '2026-09-01',
            jamPelajaran: null,
            materi: 'Kedua',
            pengguna: $this->sysadmin,
        );
    }

    public function test_jurnal_sama_tanggal_beda_jam_boleh(): void
    {
        $this->actingAs($this->sysadmin);
        $penugasan = $this->buatPenugasan();
        $jam1 = \App\Models\JamPelajaran::create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'cabang_id' => $this->cabang->id,
            'hari' => 'senin', 'urutan' => 1,
            'jam_mulai' => '07:30:00', 'jam_selesai' => '08:15:00',
            'tipe' => 'belajar', 'nama' => 'Jam 1', 'status' => 'aktif',
        ]);
        $jam2 = \App\Models\JamPelajaran::create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'cabang_id' => $this->cabang->id,
            'hari' => 'senin', 'urutan' => 2,
            'jam_mulai' => '08:15:00', 'jam_selesai' => '09:00:00',
            'tipe' => 'belajar', 'nama' => 'Jam 2', 'status' => 'aktif',
        ]);

        app(JurnalMengajarService::class)->buatJurnal(
            penugasan: $penugasan, tanggal: '2026-09-01', jamPelajaran: $jam1,
            materi: 'Jam 1', pengguna: $this->sysadmin,
        );
        $jurnal2 = app(JurnalMengajarService::class)->buatJurnal(
            penugasan: $penugasan, tanggal: '2026-09-01', jamPelajaran: $jam2,
            materi: 'Jam 2', pengguna: $this->sysadmin,
        );

        $this->assertNotNull($jurnal2->id);
        $this->assertCount(2, JurnalMengajar::where('penugasan_mengajar_id', $penugasan->id)->where('tanggal', '2026-09-01')->get());
    }

    public function test_presensi_harian_unik_siswa_tanggal(): void
    {
        $this->actingAs($this->sysadmin);
        $jurnal = JurnalMengajar::create([
            'penugasan_mengajar_id' => $this->buatPenugasan()->id,
            'cabang_id' => $this->cabang->id,
            'tanggal' => '2026-09-01',
            'jam_pelajaran_id' => null,
            'status' => 'hadir',
        ]);

        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $this->rombel,
            tanggal: '2026-09-01',
            data: [['siswa_id' => $this->siswa->id, 'status' => 'hadir']],
            jurnal: $jurnal,
            dicatatOleh: $this->sysadmin,
        );

        $this->expectException(PresensiSiswaException::class);
        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $this->rombel,
            tanggal: '2026-09-01',
            data: [['siswa_id' => $this->siswa->id, 'status' => 'izin']],
            jurnal: null,
            dicatatOleh: $this->sysadmin,
        );
    }

    public function test_presensi_per_jam_unik_siswa_tanggal_jurnal(): void
    {
        $this->actingAs($this->sysadmin);
        $penugasan = $this->buatPenugasan();
        $jam = \App\Models\JamPelajaran::create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'cabang_id' => $this->cabang->id,
            'hari' => 'senin', 'urutan' => 1,
            'jam_mulai' => '07:30:00', 'jam_selesai' => '08:15:00',
            'tipe' => 'belajar', 'nama' => 'Jam 1', 'status' => 'aktif',
        ]);
        $jurnal = app(JurnalMengajarService::class)->buatJurnal(
            penugasan: $penugasan, tanggal: '2026-09-01', jamPelajaran: $jam,
            materi: 'PKN', pengguna: $this->sysadmin,
        );

        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $this->rombel, tanggal: '2026-09-01',
            data: [['siswa_id' => $this->siswa->id, 'status' => 'hadir']],
            jurnal: $jurnal,
            dicatatOleh: $this->sysadmin,
        );

        $jam2 = \App\Models\JamPelajaran::create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'cabang_id' => $this->cabang->id,
            'hari' => 'senin', 'urutan' => 2,
            'jam_mulai' => '08:15:00', 'jam_selesai' => '09:00:00',
            'tipe' => 'belajar', 'nama' => 'Jam 2', 'status' => 'aktif',
        ]);
        $jurnal2 = app(JurnalMengajarService::class)->buatJurnal(
            penugasan: $penugasan, tanggal: '2026-09-01', jamPelajaran: $jam2,
            materi: 'Matematika', pengguna: $this->sysadmin,
        );
        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $this->rombel, tanggal: '2026-09-01',
            data: [['siswa_id' => $this->siswa->id, 'status' => 'hadir']],
            jurnal: $jurnal2,
            dicatatOleh: $this->sysadmin,
        );

        $this->assertCount(2, PresensiSiswa::where('siswa_id', $this->siswa->id)->where('tanggal', '2026-09-01')->get());
    }

    public function test_presensi_siswa_bukan_anggota_rombel_ditolak(): void
    {
        $this->expectException(PresensiSiswaException::class);
        $this->actingAs($this->sysadmin);
        $siswaLain = Siswa::create([
            'orang_id' => Orang::create(['nama_lengkap' => 'Siswa Lain', 'jenis_kelamin' => 'P'])->id,
            'nis_yayasan' => '20260002',
            'tanggal_masuk_yayasan' => '2026-07-01',
            'status' => 'aktif',
        ]);
        $jurnal = JurnalMengajar::create([
            'penugasan_mengajar_id' => $this->buatPenugasan()->id,
            'cabang_id' => $this->cabang->id,
            'tanggal' => '2026-09-01',
            'status' => 'hadir',
        ]);

        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $this->rombel, tanggal: '2026-09-01',
            data: [['siswa_id' => $siswaLain->id, 'status' => 'hadir']],
            jurnal: $jurnal,
            dicatatOleh: $this->sysadmin,
        );
    }

    public function test_presensi_dapat_dikoreksi(): void
    {
        $this->actingAs($this->sysadmin);
        $jurnal = JurnalMengajar::create([
            'penugasan_mengajar_id' => $this->buatPenugasan()->id,
            'cabang_id' => $this->cabang->id,
            'tanggal' => '2026-09-01',
            'status' => 'hadir',
        ]);
        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $this->rombel, tanggal: '2026-09-01',
            data: [['siswa_id' => $this->siswa->id, 'status' => 'hadir']],
            jurnal: $jurnal,
            dicatatOleh: $this->sysadmin,
        );

        $presensi = PresensiSiswa::where('siswa_id', $this->siswa->id)->where('tanggal', '2026-09-01')->first();
        $diperbarui = app(PresensiSiswaService::class)->koreksiPresensi(
            $presensi,
            ['status' => 'izin', 'keterangan' => 'Sakit'],
            $this->sysadmin,
        );

        $this->assertSame('izin', $diperbarui->status);
        $this->assertSame('Sakit', $diperbarui->keterangan);
        $this->assertDatabaseHas('presensi_siswa', [
            'id' => $presensi->id,
            'status' => 'izin',
            'keterangan' => 'Sakit',
            'dicatat_oleh' => $this->sysadmin->id,
        ]);
    }

    public function test_rekap_presensi_per_rombel(): void
    {
        $this->actingAs($this->sysadmin);
        $jurnal = JurnalMengajar::create([
            'penugasan_mengajar_id' => $this->buatPenugasan()->id,
            'cabang_id' => $this->cabang->id,
            'tanggal' => '2026-09-01',
            'status' => 'hadir',
        ]);
        $jurnal2 = JurnalMengajar::create([
            'penugasan_mengajar_id' => $this->buatPenugasan()->id,
            'cabang_id' => $this->cabang->id,
            'tanggal' => '2026-09-02',
            'status' => 'hadir',
        ]);

        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $this->rombel, tanggal: '2026-09-01',
            data: [['siswa_id' => $this->siswa->id, 'status' => 'hadir']],
            jurnal: $jurnal,
            dicatatOleh: $this->sysadmin,
        );
        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $this->rombel, tanggal: '2026-09-02',
            data: [['siswa_id' => $this->siswa->id, 'status' => 'alpa']],
            jurnal: $jurnal2,
            dicatatOleh: $this->sysadmin,
        );

        $rekap = app(PresensiSiswaService::class)->rekapPresensi(
            $this->rombel, '2026-09-01', '2026-09-02',
        );

        $this->assertArrayHasKey($this->siswa->orang->nama_lengkap, $rekap);
        $this->assertSame(2, $rekap[$this->siswa->orang->nama_lengkap]['total']);
        $this->assertSame(1, $rekap[$this->siswa->orang->nama_lengkap]['hadir']);
        $this->assertSame(1, $rekap[$this->siswa->orang->nama_lengkap]['alpa']);
    }

    public function test_izin_siswa_rentang_tanggal_disetujui_ditolak_dibatalkan(): void
    {
        $this->actingAs($this->sysadmin);
        $orangOrtu = Orang::create(['nama_lengkap' => 'Orang Tua', 'jenis_kelamin' => 'L']);

        $izin = app(IzinSiswaService::class)->ajukanIzin(
            siswa: $this->siswa,
            tanggalMulai: '2026-09-01',
            tanggalSelesai: '2026-09-03',
            jenis: 'sakit',
            alasan: 'Demam',
            diajukanOleh: $orangOrtu,
        );

        $this->assertSame('diajukan', $izin->status);
        $this->assertSame('2026-09-03', $izin->tanggal_selesai->toDateString());

        $disetujui = app(IzinSiswaService::class)->setujuiIzin($izin, $this->sysadmin);
        $this->assertSame('disetujui', $disetujui->status);

        $this->expectException(IzinSiswaException::class);
        app(IzinSiswaService::class)->tolakIzin($izin, $this->sysadmin, 'Alasan');
    }

    public function test_izin_tolak_dan_batal_butuh_alasan(): void
    {
        $this->actingAs($this->sysadmin);
        $orangOrtu = Orang::create(['nama_lengkap' => 'Orang Tua', 'jenis_kelamin' => 'L']);

        $izin = app(IzinSiswaService::class)->ajukanIzin(
            siswa: $this->siswa,
            tanggalMulai: '2026-09-01',
            tanggalSelesai: '2026-09-03',
            jenis: 'izin',
            alasan: 'Ujian',
            diajukanOleh: $orangOrtu,
        );

        $this->expectException(IzinSiswaException::class);
        app(IzinSiswaService::class)->tolakIzin($izin, $this->sysadmin, '');

        $this->expectException(IzinSiswaException::class);
        app(IzinSiswaService::class)->batalkanIzin($izin, $this->sysadmin, '');
    }

    public function test_izin_tanggal_selesai_sebelum_mulai_ditolak(): void
    {
        $this->expectException(IzinSiswaException::class);
        $this->actingAs($this->sysadmin);
        $orangOrtu = Orang::create(['nama_lengkap' => 'Orang Tua', 'jenis_kelamin' => 'L']);

        app(IzinSiswaService::class)->ajukanIzin(
            siswa: $this->siswa,
            tanggalMulai: '2026-09-05',
            tanggalSelesai: '2026-09-01',
            jenis: 'sakit',
            alasan: 'Sakit',
            diajukanOleh: $orangOrtu,
        );
    }

    public function test_izolasi_cabang_presensi(): void
    {
        $this->actingAs($this->sysadmin);
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
            'orang_id' => Orang::create(['nama_lengkap' => 'Siswa Cabang 2', 'jenis_kelamin' => 'L'])->id,
            'nis_yayasan' => '20260003',
            'tanggal_masuk_yayasan' => '2026-07-01',
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

        $jurnal1 = JurnalMengajar::create([
            'penugasan_mengajar_id' => $this->buatPenugasan()->id,
            'cabang_id' => $this->cabang->id,
            'tanggal' => '2026-09-01',
            'status' => 'hadir',
        ]);
        $penugasanLain = PenugasanMengajar::create([
            'sekolah_id' => $sekolahLain->id,
            'cabang_id' => $this->cabangLain->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $rombelLain->id,
            'mata_pelajaran_id' => $this->mataPelajaran->id,
            'pegawai_id' => $this->pegawai->id,
            'peran' => 'utama',
            'status' => 'aktif',
        ]);
        $jurnal2 = JurnalMengajar::create([
            'penugasan_mengajar_id' => $penugasanLain->id,
            'cabang_id' => $this->cabangLain->id,
            'tanggal' => '2026-09-01',
            'status' => 'hadir',
        ]);

        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $this->rombel, tanggal: '2026-09-01',
            data: [['siswa_id' => $this->siswa->id, 'status' => 'hadir']],
            jurnal: $jurnal1,
            dicatatOleh: $this->sysadmin,
        );
        app(PresensiSiswaService::class)->catatPresensi(
            rombel: $rombelLain, tanggal: '2026-09-01',
            data: [['siswa_id' => $siswaLain->id, 'status' => 'hadir']],
            jurnal: $jurnal2,
            dicatatOleh: $this->sysadmin,
        );

        // Konteks cabang 1 (mirip pengguna yang hanya berhak cabangnya): scope CabangKonteks
        app(\App\Support\CabangKonteks::class)->tetapkan([$this->cabang->id]);

        $this->assertCount(1, PresensiSiswa::all());
        $this->assertCount(1, JurnalMengajar::all());
    }
}


