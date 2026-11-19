<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiPegawai;
use App\Models\Cabang;
use App\Models\Jabatan;
use App\Models\JenisCuti;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\PengajuanCuti;
use App\Models\PengajuanIzin;
use App\Models\PenugasanPegawai;
use App\Models\SaldoCuti;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\PenugasanPeran;
use App\Services\Sdm\CutiService;
use App\Services\Sdm\IzinService;
use Database\Seeders\PeranSeeder;
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
    private User $pegawaiUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferensiSeeder::class);
        $this->seed(PeranSeeder::class);

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
        $this->pegawaiUser = User::create([
            'orang_id' => $orang->id,
            'username' => 'budi',
            'password' => 'pw',
        ]);

        $jabatan = Jabatan::create([
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
        app(PenugasanPeran::class)->berikan($this->penyetuju, Peran::KepalaCabang, $this->cabang->id);
    }

    private function jenisCuti(string $kode, ?int $kuota = 12): JenisCuti
    {
        return JenisCuti::create([
            'kode' => $kode,
            'nama' => 'Cuti '.$kode,
            'kuota_hari_tahunan' => $kuota,
        ]);
    }

    private function buatPengajuanCuti(?User $pengaju = null, array $sesiPerTanggal = []): PengajuanCuti
    {
        return app(CutiService::class)->ajukan(
            $this->pegawai,
            $this->jenisCuti('CT'),
            '2027-03-01',
            '2027-03-03',
            'Liburan keluarga',
            null,
            $sesiPerTanggal,
            $pengaju,
        );
    }

    private function atasanKonteks(): array
    {
        $this->penugasan->update(['tmt' => today()->subDay(), 'is_utama' => false]);

        $kepalaUnit = Pegawai::create([
            'orang_id' => Orang::create(['nama_lengkap' => 'Kepala Unit', 'jenis_kelamin' => 'L'])->id,
            'nip_yayasan' => 'NIP-KEPALA',
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
            'status' => 'aktif',
        ]);
        $userAtasan = User::create([
            'orang_id' => $kepalaUnit->orang_id,
            'username' => 'kepala_unit',
            'password' => 'pw',
        ]);

        $unit = UnitKerja::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'UNIT-A',
            'tipe' => 'instansi',
            'nama' => 'Unit A',
            'kepala_pegawai_id' => $kepalaUnit->id,
        ]);
        Jabatan::create(['kode' => 'KEPALA', 'nama' => 'Kepala Unit', 'kategori' => 'struktural', 'is_aktif' => true]);
        PenugasanPegawai::create([
            'pegawai_id' => $this->pegawai->id,
            'cabang_id' => $this->cabang->id,
            'unit_kerja_id' => $unit->id,
            'jabatan_id' => Jabatan::where('kode', 'KEPALA')->first()->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);

        return ['pegawai' => $kepalaUnit, 'user' => $userAtasan, 'unit' => $unit];
    }

    public function test_ajukan_cuti_hitung_hari_dari_tanggal_dan_sesi(): void
    {
        $pengajuan = $this->buatPengajuanCuti(null, ['2027-03-02' => 'pagi']);

        $this->assertInstanceOf(PengajuanCuti::class, $pengajuan);
        $this->assertEquals('diajukan', $pengajuan->status);
        $this->assertSame('2.5', $pengajuan->jumlah_hari);
        $this->assertSame(1, $pengajuan->jumlah_setengah_hari);
        $this->assertCount(3, $pengajuan->hari);
        $hari = $pengajuan->hari->first(fn ($h) => $h->tanggal->toDateString() === '2027-03-02');
        $this->assertSame('terhitung', $hari->status);
        $this->assertSame('0.50', $hari->nilai);

        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $pengajuan->jenis_cuti_id,
            'tahun' => 2027,
            'kuota' => '12.0',
            'terpakai' => '0.0',
        ]);
    }

    public function test_ajukan_cuti_melebihi_saldo_ditolak(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Sisa cuti tahun 2027 tidak mencukupi');

        $jenis = $this->jenisCuti('CT', 5);

        app(CutiService::class)->ajukan(
            $this->pegawai,
            $jenis,
            '2027-03-01',
            '2027-03-10',
            'Liburan panjang',
        );
    }

    public function test_pengajuan_cuti_sendiri_dilarang_bukan_kepala_cabang(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Pengajuan cuti/izin sendiri hanya dapat diproses oleh kepala cabang.');

        $orangLain = Orang::create(['nama_lengkap' => 'Guru Lain', 'jenis_kelamin' => 'L']);
        $pegawaiLain = Pegawai::create([
            'orang_id' => $orangLain->id,
            'nip_yayasan' => 'NIP-GURU-LAIN',
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
            'status' => 'aktif',
        ]);
        $jabatanLain = Jabatan::create(['kode' => 'GURU-LAIN', 'nama' => 'Guru Lain', 'kategori' => 'fungsional', 'is_aktif' => true]);
        PenugasanPegawai::create([
            'pegawai_id' => $pegawaiLain->id,
            'cabang_id' => $this->cabang->id,
            'jabatan_id' => $jabatanLain->id,
            'tmt' => today(),
            'is_utama' => true,
        ]);
        $userGuru = User::create([
            'orang_id' => $orangLain->id,
            'username' => 'guru_lain',
            'password' => 'pw',
        ]);
        app(PenugasanPeran::class)->berikan($userGuru, Peran::Guru, $this->cabang->id);

        app(CutiService::class)->ajukan(
            $pegawaiLain,
            $this->jenisCuti('CT'),
            '2027-03-01',
            '2027-03-02',
            'Liburan',
            null,
            [],
            $userGuru,
        );
    }

    public function test_pengajuan_cuti_sendiri_diizinkan_untuk_kepala_cabang_cabangnya(): void
    {
        $pengajuan = app(CutiService::class)->ajukan(
            $this->pegawai,
            $this->jenisCuti('CT'),
            '2027-03-01',
            '2027-03-02',
            'Liburan sendiri',
            null,
            [],
            $this->penyetuju,
        );

        $this->assertEquals('diajukan', $pengajuan->status);
    }

    public function test_pengajuan_cuti_tanggal_lalu_ditolak(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Pengajuan cuti untuk tanggal yang sudah lewat tidak diizinkan.');

        app(CutiService::class)->ajukan(
            $this->pegawai,
            $this->jenisCuti('CT'),
            today()->subDay()->toDateString(),
            today()->toDateString(),
            'Liburan',
            null,
            [],
            $this->pegawaiUser,
        );
    }

    public function test_setujui_atasan_lalu_final_potong_saldo_dan_tandai_absensi(): void
    {
        $atasan = $this->atasanKonteks();
        $pengajuan = $this->buatPengajuanCuti();

        $pengajuan = app(CutiService::class)->setujuiAtasan($pengajuan, $atasan['user']);
        $this->assertEquals('disetujui_atasan', $pengajuan->fresh()->status);
        $this->assertSame($atasan['user']->id, $pengajuan->disetujui_atasan_oleh);

        $pengajuan = app(CutiService::class)->setujuiFinal($pengajuan, $this->penyetuju);
        $this->assertEquals('disetujui', $pengajuan->fresh()->status);
        $this->assertSame($this->penyetuju->id, $pengajuan->disetujui_oleh);

        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $pengajuan->jenis_cuti_id,
            'tahun' => 2027,
            'terpakai' => '3.0',
        ]);
        $this->assertDatabaseHas('mutasi_saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $pengajuan->jenis_cuti_id,
            'tahun' => 2027,
            'tipe' => 'pemakaian',
            'pengajuan_cuti_id' => $pengajuan->id,
        ]);

        foreach (['2027-03-01', '2027-03-02', '2027-03-03'] as $tanggal) {
            $this->assertDatabaseHas('absensi_pegawai', [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => $tanggal,
                'status' => 'cuti',
            ]);
        }
    }

    public function test_setujui_final_ditolak_bila_atasan_sudah_ada_belum_disetujui(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Pengajuan cuti harus disetujui atasan langsung terlebih dahulu.');

        $this->atasanKonteks();
        $pengajuan = $this->buatPengajuanCuti();

        app(CutiService::class)->setujuiFinal($pengajuan, $this->penyetuju);
    }

    public function test_setujui_atasan_ditolak_bukan_atasan_langsung(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Anda tidak berhak menyetujui sebagai atasan langsung.');

        $this->atasanKonteks();
        $pengajuan = $this->buatPengajuanCuti();

        $orangLain = Orang::create(['nama_lengkap' => 'Bukan Atasan', 'jenis_kelamin' => 'L']);
        $userLain = User::create([
            'orang_id' => $orangLain->id,
            'username' => 'bukan_atasan',
            'password' => 'pw',
        ]);
        app(PenugasanPeran::class)->berikan($userLain, Peran::KepalaCabang, $this->cabang->id);

        app(CutiService::class)->setujuiAtasan($pengajuan, $userLain);
    }

    public function test_setujui_final_ditolak_bukan_peran_status(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Anda tidak berhak memberi persetujuan akhir.');

        $atasan = $this->atasanKonteks();
        $pengajuan = $this->buatPengajuanCuti();
        app(CutiService::class)->setujuiAtasan($pengajuan, $atasan['user']);

        $orangAdmin = Orang::create(['nama_lengkap' => 'Admin', 'jenis_kelamin' => 'L']);
        $userAdmin = User::create([
            'orang_id' => $orangAdmin->id,
            'username' => 'admin_sdm',
            'password' => 'pw',
        ]);
        app(PenugasanPeran::class)->berikan($userAdmin, Peran::Admin, $this->cabang->id);

        app(CutiService::class)->setujuiFinal($pengajuan, $userAdmin);
    }

    public function test_tolak_cuti_wajib_alasan_dan_tidak_potong_saldo(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Alasan penolakan wajib diisi.');

        $pengajuan = $this->buatPengajuanCuti();
        app(CutiService::class)->tolak($pengajuan, $this->penyetuju, null);
    }

    public function test_tolak_cuti_tidak_memotong_saldo(): void
    {
        $pengajuan = $this->buatPengajuanCuti();
        $pengajuan = app(CutiService::class)->tolak($pengajuan, $this->penyetuju, 'Banyak tugas');

        $this->assertEquals('ditolak', $pengajuan->fresh()->status);
        $this->assertSame('Banyak tugas', $pengajuan->fresh()->alasan_penolakan);
        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $pengajuan->jenis_cuti_id,
            'tahun' => 2027,
            'terpakai' => '0.0',
        ]);
        $this->assertDatabaseMissing('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'status' => 'cuti',
        ]);
    }

    public function test_batalkan_cuti_diajukan_wajib_alasan(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Alasan pembatalan wajib diisi.');

        $pengajuan = $this->buatPengajuanCuti();
        app(CutiService::class)->batalkan($pengajuan, $this->pegawaiUser, null);
    }

    public function test_batalkan_cuti_diajukan_oleh_pemohon(): void
    {
        $pengajuan = $this->buatPengajuanCuti();
        $pengajuan = app(CutiService::class)->batalkan($pengajuan, $this->pegawaiUser, 'Berubah pikiran');

        $this->assertEquals('dibatalkan', $pengajuan->fresh()->status);
        $this->assertSame('Berubah pikiran', $pengajuan->fresh()->alasan_pembatalan);
        $this->assertDatabaseMissing('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $pengajuan->jenis_cuti_id,
            'terpakai' => '3.0',
        ]);
    }

    public function test_batalkan_cuti_disetujui_kembalikan_saldo_dan_hapus_absensi(): void
    {
        $pengajuan = $this->buatPengajuanCuti();
        $pengajuan = app(CutiService::class)->setujuiFinal($pengajuan, $this->penyetuju);

        $this->assertEquals('disetujui', $pengajuan->fresh()->status);
        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $pengajuan->jenis_cuti_id,
            'tahun' => 2027,
            'terpakai' => '3.0',
        ]);
        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2027-03-01',
            'status' => 'cuti',
        ]);

        $pengajuan = app(CutiService::class)->batalkan($pengajuan, $this->penyetuju, 'Keadaan paksa');

        $this->assertEquals('dibatalkan', $pengajuan->fresh()->status);
        $this->assertSame('Keadaan paksa', $pengajuan->fresh()->alasan_pembatalan);
        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $pengajuan->jenis_cuti_id,
            'tahun' => 2027,
            'terpakai' => '0.0',
        ]);
        $this->assertDatabaseHas('mutasi_saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $pengajuan->jenis_cuti_id,
            'tahun' => 2027,
            'tipe' => 'pengembalian',
            'pengajuan_cuti_id' => $pengajuan->id,
        ]);
        $this->assertDatabaseMissing('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2027-03-01',
            'status' => 'cuti',
        ]);
    }

    public function test_izin_ajukan_setujui_atasan_final_dan_tandai_absensi(): void
    {
        $atasan = $this->atasanKonteks();
        $pengajuan = app(IzinService::class)->ajukan(
            $this->pegawai,
            'sakit',
            '2027-03-01',
            '2027-03-02',
            'Demam tinggi',
        );

        $this->assertEquals('diajukan', $pengajuan->status);

        $pengajuan = app(IzinService::class)->setujuiAtasan($pengajuan, $atasan['user']);
        $this->assertEquals('disetujui_atasan', $pengajuan->fresh()->status);

        $pengajuan = app(IzinService::class)->setujuiFinal($pengajuan, $this->penyetuju);
        $this->assertEquals('disetujui', $pengajuan->fresh()->status);

        foreach (['2027-03-01', '2027-03-02'] as $tanggal) {
            $this->assertDatabaseHas('absensi_pegawai', [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => $tanggal,
                'status' => 'sakit',
            ]);
        }
    }

    public function test_izin_tolak_wajib_alasan_dan_tidak_buat_absensi(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Alasan penolakan wajib diisi.');

        $pengajuan = app(IzinService::class)->ajukan(
            $this->pegawai,
            'izin',
            '2027-03-01',
            '2027-03-01',
            'Keperluan pribadi',
        );
        app(IzinService::class)->tolak($pengajuan, $this->penyetuju, null);
    }

    public function test_izin_tolak_status_dan_tanpa_absensi(): void
    {
        $pengajuan = app(IzinService::class)->ajukan(
            $this->pegawai,
            'dinas_luar',
            '2027-03-01',
            '2027-03-01',
            'Surat',
        );
        $pengajuan = app(IzinService::class)->tolak($pengajuan, $this->penyetuju, 'Tidak diperlukan');

        $this->assertEquals('ditolak', $pengajuan->fresh()->status);
        $this->assertDatabaseMissing('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'status' => 'dinas',
        ]);
    }

    public function test_batalkan_izin_disetujui_hapus_absensi(): void
    {
        $pengajuan = app(IzinService::class)->ajukan(
            $this->pegawai,
            'izin',
            '2027-03-01',
            '2027-03-01',
            'Keperluan',
        );
        $pengajuan = app(IzinService::class)->setujuiFinal($pengajuan, $this->penyetuju);

        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2027-03-01',
            'status' => 'izin',
        ]);

        $pengajuan = app(IzinService::class)->batalkan($pengajuan, $this->penyetuju, 'Dibatalkan');

        $this->assertEquals('dibatalkan', $pengajuan->fresh()->status);
        $this->assertDatabaseMissing('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2027-03-01',
            'status' => 'izin',
        ]);
    }

    public function test_cuti_dan_izin_tidak_boleh_tumpang_tindih(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Sudah ada pengajuan cuti pada rentang tanggal tersebut.');

        $this->buatPengajuanCuti();

        app(IzinService::class)->ajukan(
            $this->pegawai,
            'izin',
            '2027-03-02',
            '2027-03-04',
            'Izin',
        );
    }

    public function test_saldo_cuti_dibawa_ke_tahun_berikutnya(): void
    {
        $jenis = $this->jenisCuti('CT', 12);

        SaldoCuti::create([
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'tahun' => 2026,
            'kuota' => '12.0',
            'terpakai' => '3.0',
            'dibawa' => '0.0',
        ]);

        $this->assertDatabaseHas('saldo_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'tahun' => 2026,
            'terpakai' => '3.0',
        ]);

        app(\App\Services\Sdm\MutasiSaldoCutiService::class)->ambilAtauBuat($this->pegawai, $jenis, 2027);

        $saldo2027 = SaldoCuti::query()
            ->where('pegawai_id', $this->pegawai->id)
            ->where('jenis_cuti_id', $jenis->id)
            ->where('tahun', 2027)
            ->first();

        $this->assertNotNull($saldo2027);
        $this->assertSame('12.0', $saldo2027->kuota);
        $this->assertSame('9.0', $saldo2027->dibawa);
        $this->assertSame('21.0', $saldo2027->sisa());
    }
}
