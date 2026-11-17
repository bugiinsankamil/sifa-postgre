<?php

namespace Tests\Feature;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiLogMentah;
use App\Models\AbsensiPegawai;
use App\Models\Cabang;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\PenugasanPegawai;
use App\Models\ShiftKerja;
use App\Services\Sdm\AbsensiLogProcessor;
use App\Services\Sdm\AbsensiService;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsensiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;
    private Pegawai $pegawai;
    private PenugasanPegawai $penugasan;

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
    }

    public function test_impor_log_mentah(): void
    {
        $log = app(AbsensiLogProcessor::class)->imporLog(
            $this->pegawai,
            null,
            now()->format('Y-m-d 07:00:00'),
            'masuk',
            'mesin'
        );

        $this->assertInstanceOf(AbsensiLogMentah::class, $log);
        $this->assertEquals('masuk', $log->tipe);
        $this->assertNull($log->diproses_pada);
        $this->assertEquals($this->pegawai->id, $log->pegawai_id);
    }

    public function test_proses_log_masuk_tanpa_shift_status_hadir(): void
    {
        $log = app(AbsensiLogProcessor::class)->imporLog(
            $this->pegawai,
            null,
            now()->format('Y-m-d 07:00:00'),
            'masuk',
            'mesin'
        );

        $absensi = app(AbsensiLogProcessor::class)->prosesLogMentah($log);

        $this->assertInstanceOf(AbsensiPegawai::class, $absensi);
        $this->assertEquals('hadir', $absensi->status);
        $this->assertEquals('07:00:00', $absensi->jam_masuk);
        $this->assertNotNull($log->fresh()->diproses_pada);
    }

    public function test_proses_log_masuk_dengan_shift_terlambat(): void
    {
        $shift = ShiftKerja::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Pagi',
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 5,
            'hari_kerja' => [1, 2, 3, 4, 5],
        ]);

        // Set shift ke pegawai
        \App\Models\PegawaiShift::create([
            'pegawai_id' => $this->pegawai->id,
            'shift_id' => $shift->id,
            'tanggal_mulai' => today(),
        ]);

        $log = app(AbsensiLogProcessor::class)->imporLog(
            $this->pegawai,
            null,
            now()->format('Y-m-d 07:15:00'),
            'masuk',
            'mesin'
        );

        $absensi = app(AbsensiLogProcessor::class)->prosesLogMentah($log);

        $this->assertEquals('terlambat', $absensi->status);
        $this->assertEquals(10, $absensi->terlambat_menit); // 15 menit - 5 toleransi = 10
    }

    public function test_proses_log_pulang_hitung_pulang_cepat(): void
    {
        $shift = ShiftKerja::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Pagi',
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 0,
            'hari_kerja' => [1, 2, 3, 4, 5],
        ]);

        \App\Models\PegawaiShift::create([
            'pegawai_id' => $this->pegawai->id,
            'shift_id' => $shift->id,
            'tanggal_mulai' => today(),
        ]);

        $logMasuk = app(AbsensiLogProcessor::class)->imporLog(
            $this->pegawai,
            null,
            now()->format('Y-m-d 07:00:00'),
            'masuk',
            'mesin'
        );
        app(AbsensiLogProcessor::class)->prosesLogMentah($logMasuk);

        $logPulang = app(AbsensiLogProcessor::class)->imporLog(
            $this->pegawai,
            null,
            now()->format('Y-m-d 14:30:00'),
            'pulang',
            'mesin'
        );

        $absensi = app(AbsensiLogProcessor::class)->prosesLogMentah($logPulang);

        $this->assertEquals('hadir', $absensi->status);
        $this->assertEquals('14:30:00', $absensi->jam_pulang);
        $this->assertEquals(30, $absensi->pulang_cepat_menit);
    }

    public function test_log_tidak_menimpa_status_cuti_dari_approval(): void
    {
        // Buat absensi dengan status cuti (misalnya dari approval)
        app(AbsensiService::class)->catatManual($this->pegawai, today()->toDateString(), [
            'status' => 'cuti',
            'keterangan' => 'Cuti tahunan',
        ]);

        // Log masuk pada hari yang sama
        $log = app(AbsensiLogProcessor::class)->imporLog(
            $this->pegawai,
            null,
            now()->format('Y-m-d 07:00:00'),
            'masuk',
            'mesin'
        );

        $absensi = app(AbsensiLogProcessor::class)->prosesLogMentah($log);

        $this->assertEquals('cuti', $absensi->status);
        $this->assertNull($absensi->jam_masuk); // Log tidak diisi ke absensi
        $this->assertNotNull($log->fresh()->diproses_pada); // Log tetap ditandai diproses
    }

    public function test_catat_manual_absensi_izin(): void
    {
        $absensi = app(AbsensiService::class)->catatManual($this->pegawai, today()->toDateString(), [
            'status' => 'izin',
            'keterangan' => 'Ada keperluan keluarga',
        ]);

        $this->assertEquals('izin', $absensi->status);
        $this->assertEquals('Ada keperluan keluarga', $absensi->keterangan);
        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => today()->toDateString(),
            'status' => 'izin',
        ]);
    }

    public function test_dedup_log_mentah_per_perangkat(): void
    {
        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('sudah pernah direkam');

        // Buat perangkat
        $perangkat = \App\Models\PerangkatAbsensi::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Mesin 1',
            'tipe' => 'fingerprint',
            'nomor_seri' => 'SN001',
        ]);

        $idUnik = 'SCAN-20261115-001';

        // Impor pertama
        app(AbsensiLogProcessor::class)->imporLog(
            $this->pegawai,
            $perangkat->id,
            now()->format('Y-m-d 07:00:00'),
            'masuk',
            'mesin',
            null,
            null,
            $idUnik
        );

        // Impor kedua dengan id_unik sama → harus gagal
        app(AbsensiLogProcessor::class)->imporLog(
            $this->pegawai,
            $perangkat->id,
            now()->format('Y-m-d 07:05:00'),
            'masuk',
            'mesin',
            null,
            null,
            $idUnik
        );
    }

    // ============ Paket B ============

    public function test_log_tidak_berurutan_ambil_masuk_paling_awal_dan_pulang_paling_akhir(): void
    {
        $shift = ShiftKerja::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Pagi',
            'jam_masuk' => '07:00', 'jam_pulang' => '15:00',
            'toleransi_menit' => 0, 'hari_kerja' => [1, 2, 3, 4, 5],
        ]);
        \App\Models\PegawaiShift::create([
            'pegawai_id' => $this->pegawai->id, 'shift_id' => $shift->id, 'tanggal_mulai' => today(),
        ]);

        $logMasuk1 = app(AbsensiLogProcessor::class)->imporLog($this->pegawai, null, now()->format('Y-m-d 07:10:00'), 'masuk', 'mesin');
        $logMasuk2 = app(AbsensiLogProcessor::class)->imporLog($this->pegawai, null, now()->format('Y-m-d 07:00:00'), 'masuk', 'mesin');
        $logPulang1 = app(AbsensiLogProcessor::class)->imporLog($this->pegawai, null, now()->format('Y-m-d 14:00:00'), 'pulang', 'mesin');
        $logPulang2 = app(AbsensiLogProcessor::class)->imporLog($this->pegawai, null, now()->format('Y-m-d 15:30:00'), 'pulang', 'mesin');

        $processor = app(AbsensiLogProcessor::class);
        $processor->prosesLogMentah($logMasuk1);
        $processor->prosesLogMentah($logMasuk2);
        $processor->prosesLogMentah($logPulang1);
        $processor->prosesLogMentah($logPulang2);

        $absensi = AbsensiPegawai::where('pegawai_id', $this->pegawai->id)->where('tanggal', today()->toDateString())->first();
        $this->assertEquals('07:00:00', $absensi->jam_masuk);
        $this->assertEquals('15:30:00', $absensi->jam_pulang);
        $this->assertEquals('hadir', $absensi->status);
        $this->assertEquals(0, $absensi->terlambat_menit);
        $this->assertEquals(0, $absensi->pulang_cepat_menit);
    }

    public function test_pulang_cepat_dihitung_untuk_status_terlambat(): void
    {
        $shift = ShiftKerja::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Pagi',
            'jam_masuk' => '07:00', 'jam_pulang' => '15:00',
            'toleransi_menit' => 0, 'hari_kerja' => [1, 2, 3, 4, 5],
        ]);
        \App\Models\PegawaiShift::create([
            'pegawai_id' => $this->pegawai->id, 'shift_id' => $shift->id, 'tanggal_mulai' => today(),
        ]);

        $logMasuk = app(AbsensiLogProcessor::class)->imporLog($this->pegawai, null, now()->format('Y-m-d 07:15:00'), 'masuk', 'mesin');
        $logPulang = app(AbsensiLogProcessor::class)->imporLog($this->pegawai, null, now()->format('Y-m-d 14:30:00'), 'pulang', 'mesin');

        $processor = app(AbsensiLogProcessor::class);
        $processor->prosesLogMentah($logMasuk);
        $absensi = $processor->prosesLogMentah($logPulang);

        $this->assertEquals('terlambat', $absensi->status);
        $this->assertEquals(15, $absensi->terlambat_menit);
        $this->assertEquals(30, $absensi->pulang_cepat_menit);
    }

    public function test_dedup_database_mencegah_double_insert(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $perangkat = \App\Models\PerangkatAbsensi::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Mesin 1', 'tipe' => 'fingerprint', 'nomor_seri' => 'SN001',
        ]);
        $idUnik = 'SCAN-DB-DEDUP';

        // Impor pertama lewat service
        app(AbsensiLogProcessor::class)->imporLog($this->pegawai, $perangkat->id, now()->format('Y-m-d 07:00:00'), 'masuk', 'mesin', null, null, $idUnik);

        // Impor kedua via model langsung — service check akan menangkapnya duluan,
        // tapi ini menguji bahwa constraint database juga melindungi
        AbsensiLogMentah::create([
            'cabang_id' => $this->cabang->id,
            'perangkat_id' => $perangkat->id,
            'pegawai_id' => $this->pegawai->id,
            'waktu' => now()->format('Y-m-d 07:05:00'),
            'tipe' => 'masuk',
            'metode' => 'mesin',
            'id_unik_perangkat' => $idUnik,
        ]);
    }

    public function test_command_proses_semua_log_mentah(): void
    {
        $log1 = app(AbsensiLogProcessor::class)->imporLog($this->pegawai, null, now()->format('Y-m-d 07:00:00'), 'masuk', 'mesin');
        $log2 = app(AbsensiLogProcessor::class)->imporLog($this->pegawai, null, now()->format('Y-m-d 15:00:00'), 'pulang', 'mesin');

        $jumlah = app(AbsensiLogProcessor::class)->prosesSemuaLogMentah();

        $this->assertEquals(2, $jumlah);
        $this->assertNotNull($log1->fresh()->diproses_pada);
        $this->assertNotNull($log2->fresh()->diproses_pada);
        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $this->pegawai->id, 'tanggal' => today()->toDateString(), 'status' => 'hadir',
        ]);
    }

    public function test_catat_manual_terlambat_tidak_negatif(): void
    {
        $shift = ShiftKerja::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Pagi',
            'jam_masuk' => '07:00', 'jam_pulang' => '15:00',
            'toleransi_menit' => 5, 'hari_kerja' => [1, 2, 3, 4, 5],
        ]);
        \App\Models\PegawaiShift::create([
            'pegawai_id' => $this->pegawai->id, 'shift_id' => $shift->id, 'tanggal_mulai' => today(),
        ]);

        $absensi = app(AbsensiService::class)->catatManual($this->pegawai, today()->toDateString(), [
            'status' => 'hadir', 'jam_masuk' => '07:15:00',
        ]);

        $this->assertGreaterThanOrEqual(0, $absensi->terlambat_menit);
        $this->assertEquals(10, $absensi->terlambat_menit); // 07:15 - 07:05 = 10
    }
}
