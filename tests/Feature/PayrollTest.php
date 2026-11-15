<?php

namespace Tests\Feature;

use App\Exceptions\Sdm\SdmException;
use App\Models\Cabang;
use App\Models\GajiPegawai;
use App\Models\KomponenGaji;
use App\Models\Orang;
use App\Models\Pegawai;
use App\Models\PeriodeGaji;
use App\Models\SkemaGaji;
use App\Models\SkemaGajiKomponen;
use App\Models\SlipGaji;
use App\Services\Sdm\PayrollService;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
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
            'orang_id' => $orang->id,
            'nip_yayasan' => 'NIP-BUDI',
            'tipe' => 'guru',
            'status_kepegawaian_ref' => 'tetap',
            'tanggal_masuk' => today(),
            'status' => 'aktif',
        ]);
    }

    public function test_tetapkan_gaji_pokok(): void
    {
        $gaji = app(PayrollService::class)->tetapkanGaji($this->pegawai, 5000000, today()->toDateString());

        $this->assertInstanceOf(GajiPegawai::class, $gaji);
        $this->assertEquals('5000000.00', $gaji->gaji_pokok);
        $this->assertEquals('aktif', $gaji->status);
    }

    public function test_tetapkan_gaji_baru_menonaktifkan_gaji_lama(): void
    {
        $gajiLama = app(PayrollService::class)->tetapkanGaji($this->pegawai, 5000000, today()->toDateString());
        $gajiBaru = app(PayrollService::class)->tetapkanGaji($this->pegawai, 6000000, today()->addMonth()->toDateString());

        $this->assertEquals('berakhir', $gajiLama->fresh()->status);
        $this->assertEquals('aktif', $gajiBaru->status);
    }

    public function test_hitung_gaji_tanpa_skema(): void
    {
        app(PayrollService::class)->tetapkanGaji($this->pegawai, 5000000, today()->toDateString());

        $periode = app(PayrollService::class)->buatPeriode(
            $this->cabang->id,
            2026,
            11,
            '2026-11-01',
            '2026-11-30'
        );

        $slip = app(PayrollService::class)->hitungGaji($periode, $this->pegawai);

        $this->assertInstanceOf(SlipGaji::class, $slip);
        $this->assertEquals('dihitung', $slip->status);
        $this->assertEquals('5000000.00', $slip->total_pendapatan);
        $this->assertEquals('0.00', $slip->total_potongan);
        $this->assertEquals('5000000.00', $slip->gaji_bersih);
        $this->assertCount(1, $slip->rincian); // Hanya gaji pokok
    }

    public function test_hitung_gaji_dengan_komponen_tetap(): void
    {
        $gaji = app(PayrollService::class)->tetapkanGaji($this->pegawai, 5000000, today()->toDateString());

        $skema = SkemaGaji::create(['nama' => 'Skema Guru']);
        $gaji->update(['skema_id' => $skema->id]);

        $tunjangan = KomponenGaji::create([
            'kode' => 'TUNJ',
            'nama' => 'Tunjangan Transport',
            'tipe' => 'pendapatan',
            'cara_hitung' => 'tetap',
        ]);

        SkemaGajiKomponen::create([
            'skema_id' => $skema->id,
            'komponen_id' => $tunjangan->id,
            'nominal' => 500000,
        ]);

        $periode = app(PayrollService::class)->buatPeriode(
            $this->cabang->id,
            2026,
            11,
            '2026-11-01',
            '2026-11-30'
        );

        $slip = app(PayrollService::class)->hitungGaji($periode, $this->pegawai);

        $this->assertEquals('5500000.00', $slip->total_pendapatan);
        $this->assertEquals('5500000.00', $slip->gaji_bersih);
        $this->assertCount(2, $slip->rincian); // Gaji pokok + tunjangan
    }

    public function test_hitung_gaji_dengan_potongan(): void
    {
        $gaji = app(PayrollService::class)->tetapkanGaji($this->pegawai, 5000000, today()->toDateString());

        $skema = SkemaGaji::create(['nama' => 'Skema Guru']);
        $gaji->update(['skema_id' => $skema->id]);

        $potongan = KomponenGaji::create([
            'kode' => 'POT',
            'nama' => 'Potongan Pinjaman',
            'tipe' => 'potongan',
            'cara_hitung' => 'tetap',
        ]);

        SkemaGajiKomponen::create([
            'skema_id' => $skema->id,
            'komponen_id' => $potongan->id,
            'nominal' => 500000,
        ]);

        $periode = app(PayrollService::class)->buatPeriode(
            $this->cabang->id,
            2026,
            11,
            '2026-11-01',
            '2026-11-30'
        );

        $slip = app(PayrollService::class)->hitungGaji($periode, $this->pegawai);

        $this->assertEquals('5000000.00', $slip->total_pendapatan);
        $this->assertEquals('500000.00', $slip->total_potongan);
        $this->assertEquals('4500000.00', $slip->gaji_bersih);
    }

    public function test_hitung_gaji_dengan_persen(): void
    {
        $gaji = app(PayrollService::class)->tetapkanGaji($this->pegawai, 5000000, today()->toDateString());

        $skema = SkemaGaji::create(['nama' => 'Skema Guru']);
        $gaji->update(['skema_id' => $skema->id]);

        $bpjs = KomponenGaji::create([
            'kode' => 'BPJS',
            'nama' => 'BPJS Kesehatan',
            'tipe' => 'potongan',
            'cara_hitung' => 'persen',
        ]);

        SkemaGajiKomponen::create([
            'skema_id' => $skema->id,
            'komponen_id' => $bpjs->id,
            'persen' => 1, // 1% dari gaji pokok
        ]);

        $periode = app(PayrollService::class)->buatPeriode(
            $this->cabang->id,
            2026,
            11,
            '2026-11-01',
            '2026-11-30'
        );

        $slip = app(PayrollService::class)->hitungGaji($periode, $this->pegawai);

        $this->assertEquals('50000.00', $slip->total_potongan); // 1% dari 5 juta
        $this->assertEquals('4950000.00', $slip->gaji_bersih);
    }

    public function test_setujui_dan_bayar_periode_gaji(): void
    {
        app(PayrollService::class)->tetapkanGaji($this->pegawai, 5000000, today()->toDateString());

        $periode = app(PayrollService::class)->buatPeriode(
            $this->cabang->id,
            2026,
            11,
            '2026-11-01',
            '2026-11-30'
        );

        $slip = app(PayrollService::class)->hitungGaji($periode, $this->pegawai);
        $this->assertEquals('dihitung', $slip->status);

        $periode->update(['status' => 'dihitung']);

        $periode = app(PayrollService::class)->setujuiPeriode($periode);
        $this->assertEquals('disetujui', $periode->status);
        $this->assertEquals('disetujui', $slip->fresh()->status);

        $periode = app(PayrollService::class)->bayarPeriode($periode, today()->toDateString());
        $this->assertEquals('dibayar', $periode->status);
        $this->assertEquals('dibayar', $slip->fresh()->status);
        $this->assertEquals(today()->toDateString(), $slip->fresh()->tanggal_bayar->toDateString());
    }
}
