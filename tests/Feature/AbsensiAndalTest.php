<?php

namespace Tests\Feature;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiLogMentah;
use App\Models\AbsensiPegawai;
use App\Models\Cabang;
use App\Models\Jabatan;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\PegawaiShift;
use App\Models\PenugasanPegawai;
use App\Models\ShiftKerja;
use App\Services\Sdm\AbsensiLogProcessor;
use App\Services\Sdm\AbsensiService;
use App\Services\Sdm\RekapAbsensiService;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paket B: pengolahan log andal (urutan, idempotensi, batch, log gagal) dan rekap/CSV.
 */
class AbsensiAndalTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;
    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferensiSeeder::class);
        $this->cabang = Cabang::create(['kode' => 'FTN-01', 'nama' => 'Fathona 01']);

        $orang = Orang::create(['nama_lengkap' => 'Budi', 'jenis_kelamin' => 'L']);
        $this->pegawai = Pegawai::create([
            'orang_id' => $orang->id, 'nip_yayasan' => 'NIP-BUDI', 'tipe' => 'guru',
            'status_kepegawaian_ref' => 'tetap', 'tanggal_masuk' => today(), 'status' => 'aktif',
        ]);
        $jabatan = Jabatan::create(['kode' => 'GURU', 'nama' => 'Guru', 'kategori' => 'fungsional', 'is_aktif' => true]);
        PenugasanPegawai::create([
            'pegawai_id' => $this->pegawai->id, 'cabang_id' => $this->cabang->id,
            'jabatan_id' => $jabatan->id, 'tmt' => today(), 'is_utama' => true,
        ]);
    }

    private function pasangShift(string $masuk = '07:00', string $pulang = '15:00', int $toleransi = 0): void
    {
        $shift = ShiftKerja::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Pagi', 'jam_masuk' => $masuk,
            'jam_pulang' => $pulang, 'toleransi_menit' => $toleransi, 'hari_kerja' => [1, 2, 3, 4, 5],
        ]);
        PegawaiShift::create(['pegawai_id' => $this->pegawai->id, 'shift_id' => $shift->id, 'tanggal_mulai' => today()]);
    }

    private function impor(string $jam, string $tipe): AbsensiLogMentah
    {
        return app(AbsensiLogProcessor::class)->imporLog($this->pegawai, null, now()->format('Y-m-d '.$jam), $tipe, 'mesin');
    }

    public function test_urutan_kedatangan_log_tidak_mengubah_hasil(): void
    {
        $this->pasangShift();
        $pulang = $this->impor('14:30:00', 'pulang');
        $masuk = $this->impor('07:15:00', 'masuk');
        $masukLebihAwal = $this->impor('07:05:00', 'masuk');

        $proses = app(AbsensiLogProcessor::class);
        $proses->prosesLogMentah($pulang);
        $proses->prosesLogMentah($masuk);
        $absensi = $proses->prosesLogMentah($masukLebihAwal);

        $this->assertSame('07:05:00', $absensi->jam_masuk);
        $this->assertSame('14:30:00', $absensi->jam_pulang);
        $this->assertSame('terlambat', $absensi->status);
        $this->assertSame(5, $absensi->terlambat_menit);
        $this->assertSame(30, $absensi->pulang_cepat_menit);
        $this->assertSame(1, AbsensiPegawai::count());
    }

    public function test_proses_ulang_dan_instance_basi_tidak_menggandakan(): void
    {
        $log = $this->impor('07:00:00', 'masuk');
        $salinanBasi = AbsensiLogMentah::findOrFail($log->id);

        $pertama = app(AbsensiLogProcessor::class)->prosesLogMentah($log);
        $this->assertNotNull($pertama);

        // Instance lama masih menganggap belum diproses (mirip proses bersamaan): harus diabaikan
        $this->assertNull(app(AbsensiLogProcessor::class)->prosesLogMentah($salinanBasi));
        $this->assertSame(0, app(AbsensiLogProcessor::class)->prosesSemuaLogMentah());
        $this->assertSame(1, AbsensiPegawai::count());
    }

    public function test_command_artisan_menghormati_batas_batch(): void
    {
        $this->impor('07:00:00', 'masuk');
        $this->impor('15:00:00', 'pulang');

        $this->artisan('absensi:proses-log', ['--limit' => 1])->assertExitCode(0);
        $this->assertSame(1, AbsensiLogMentah::whereNotNull('diproses_pada')->count());

        $this->artisan('absensi:proses-log')->assertExitCode(0);
        $this->assertSame(2, AbsensiLogMentah::whereNotNull('diproses_pada')->count());

        // Dijalankan ulang tanpa log baru: tidak ada perubahan
        $this->artisan('absensi:proses-log')->assertExitCode(0);
        $this->assertSame(1, AbsensiPegawai::count());
    }

    public function test_command_terdaftar_di_scheduler_dengan_tanpa_tumpang_tindih(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'absensi:proses-log'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_log_gagal_dicatat_dan_berhenti_setelah_batas_percobaan(): void
    {
        // Tidak ada penugasan pada tanggal log (penugasan baru mulai hari ini)
        $log = AbsensiLogMentah::create([
            'cabang_id' => $this->cabang->id, 'pegawai_id' => $this->pegawai->id,
            'waktu' => now()->subDays(2)->format('Y-m-d 07:00:00'), 'tipe' => 'masuk', 'metode' => 'mesin',
        ]);

        $proses = app(AbsensiLogProcessor::class);
        $this->assertSame(0, $proses->prosesSemuaLogMentah());
        $this->assertSame(1, $proses->gagal);

        $log->refresh();
        $this->assertNull($log->diproses_pada);
        $this->assertSame(1, $log->percobaan);
        $this->assertStringContainsString('penugasan aktif', $log->galat);
        $this->assertNotNull($log->gagal_pada);

        for ($i = 0; $i < 10; $i++) {
            $proses->prosesSemuaLogMentah();
        }
        $this->assertSame(AbsensiLogProcessor::MAKS_PERCOBAAN, $log->fresh()->percobaan);

        $proses->ulangiLogGagal($log->fresh());
        $this->assertSame(0, $log->fresh()->percobaan);
        $this->assertNull($log->fresh()->galat);
    }

    public function test_log_tanpa_pegawai_tidak_memblokir_log_lain(): void
    {
        AbsensiLogMentah::create([
            'cabang_id' => $this->cabang->id, 'pegawai_id' => null, 'pin' => '99',
            'waktu' => now()->format('Y-m-d 06:00:00'), 'tipe' => 'masuk', 'metode' => 'mesin',
        ]);
        $this->impor('07:00:00', 'masuk');

        $proses = app(AbsensiLogProcessor::class);
        $this->assertSame(1, $proses->prosesSemuaLogMentah());
        $this->assertSame(1, $proses->gagal);
    }

    public function test_log_dengan_cabang_tidak_sesuai_penugasan_ditolak(): void
    {
        $lain = Cabang::create(['kode' => 'FTN-02', 'nama' => 'Fathona 02']);
        $log = AbsensiLogMentah::create([
            'cabang_id' => $lain->id, 'pegawai_id' => $this->pegawai->id,
            'waktu' => now()->format('Y-m-d 07:00:00'), 'tipe' => 'masuk', 'metode' => 'mesin',
        ]);

        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Cabang log tidak sesuai');
        app(AbsensiLogProcessor::class)->prosesLogMentah($log);
    }

    public function test_rekap_bulanan_dan_csv(): void
    {
        $this->pegawai->orang->update(['nama_lengkap' => '=1+1']);
        app(AbsensiService::class)->catatManual($this->pegawai, today()->toDateString(), ['status' => 'hadir']);

        $rekap = app(RekapAbsensiService::class);
        $baris = $rekap->rekapBulanan((int) now()->format('Y'), (int) now()->format('n'));

        $this->assertCount(1, $baris);
        $this->assertSame(1, $baris[0]['hadir']);
        $this->assertSame(0, $baris[0]['alpa']);

        $csv = $rekap->csv((int) now()->format('Y'), (int) now()->format('n'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('NIP-BUDI', $csv);
        $this->assertStringContainsString("'=1+1", $csv);
    }

    public function test_catat_manual_ditolak_untuk_pegawai_cabang_lain(): void
    {
        $lain = Cabang::create(['kode' => 'FTN-02', 'nama' => 'Fathona 02']);
        app(\App\Support\CabangKonteks::class)->tetapkan([$lain->id]);

        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('cabang lain');
        app(AbsensiService::class)->catatManual($this->pegawai, today()->toDateString(), ['status' => 'hadir']);
    }

    public function test_absensi_hasil_cuti_tidak_bisa_dikoreksi_manual(): void
    {
        $jenis = \App\Models\JenisCuti::create(['kode' => 'CT', 'nama' => 'Cuti', 'kuota_hari_tahunan' => 12]);
        $cuti = app(\App\Services\Sdm\CutiService::class)->ajukan(
            $this->pegawai, $jenis, today()->toDateString(), today()->toDateString(), 'Cuti'
        );
        $penyetuju = \App\Models\User::create([
            'orang_id' => Orang::create(['nama_lengkap' => 'Kepala', 'jenis_kelamin' => 'L'])->id,
            'username' => 'kepala', 'password' => 'pw',
        ]);
        $this->seed(\Database\Seeders\PeranSeeder::class);
        app(\App\Services\PenugasanPeran::class)->berikan($penyetuju, \App\Enums\Peran::KepalaCabang, $this->cabang->id);
        app(\App\Services\Sdm\CutiService::class)->setujuiFinal($cuti, $penyetuju);

        $this->expectException(SdmException::class);
        $this->expectExceptionMessage('Batalkan pengajuannya');
        app(AbsensiService::class)->catatManual($this->pegawai, today()->toDateString(), ['status' => 'hadir']);
    }

    public function test_rekap_menolak_bulan_tidak_valid(): void
    {
        $this->expectException(SdmException::class);
        app(RekapAbsensiService::class)->rekapBulanan(2026, 13);
    }
}
