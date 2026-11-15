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
}
