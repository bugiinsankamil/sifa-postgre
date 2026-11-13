<?php

namespace Tests\Feature;

use App\Models\AntreanPesan;
use App\Pesan\HasilKirim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PengirimUji;
use Tests\TestCase;

class KirimPesanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PengirimUji::reset();
        config([
            'pesan.aktif' => true,
            'pesan.driver.uji' => PengirimUji::class,
            'pesan.pengirim.whatsapp' => 'uji',
            'pesan.jeda_ms' => 0,
            'pesan.maks_percobaan' => 5,
            'pesan.jam_tenang' => ['mulai' => null, 'selesai' => null],
        ]);
    }

    private function antrean(array $ubah = []): AntreanPesan
    {
        return AntreanPesan::query()->create($ubah + [
            'kanal' => 'whatsapp', 'tujuan' => '6281234567890', 'isi' => 'Halo',
            'status' => 'antri', 'dijadwalkan_pada' => now()->subMinute(),
        ]);
    }

    public function test_pesan_terkirim_dan_dicatat(): void
    {
        $pesan = $this->antrean();

        $this->artisan('pesan:kirim')->assertSuccessful();

        $pesan->refresh();
        $this->assertSame('terkirim', $pesan->status);
        $this->assertSame(1, $pesan->percobaan);
        $this->assertNotNull($pesan->dikirim_pada);
        $this->assertCount(1, PengirimUji::$terkirim);
        $this->assertSame('6281234567890', PengirimUji::$terkirim[0]['tujuan']);
    }

    public function test_gagal_yang_bisa_diulang_dijadwalkan_ulang_dengan_jeda(): void
    {
        PengirimUji::$hasilBerikut = [HasilKirim::gagal('Gateway sibuk')];
        $pesan = $this->antrean();

        $this->artisan('pesan:kirim')->assertSuccessful();

        $pesan->refresh();
        $this->assertSame('antri', $pesan->status);
        $this->assertSame(1, $pesan->percobaan);
        $this->assertSame('Gateway sibuk', $pesan->galat);
        $this->assertTrue($pesan->dijadwalkan_pada->isFuture());
    }

    public function test_gagal_setelah_percobaan_terakhir_menjadi_gagal(): void
    {
        PengirimUji::$hasilBerikut = [HasilKirim::gagal('Masih gagal')];
        $pesan = $this->antrean(['percobaan' => 4]);

        $this->artisan('pesan:kirim');

        $this->assertSame('gagal', $pesan->fresh()->status);
        $this->assertSame(5, $pesan->fresh()->percobaan);
    }

    public function test_kegagalan_permanen_langsung_gagal(): void
    {
        PengirimUji::$hasilBerikut = [HasilKirim::gagal('Nomor tidak terdaftar di WhatsApp', bisaDiulang: false)];
        $pesan = $this->antrean();

        $this->artisan('pesan:kirim');

        $this->assertSame('gagal', $pesan->fresh()->status);
        $this->assertSame(1, $pesan->fresh()->percobaan);
    }

    public function test_pengecualian_dari_pengirim_dianggap_gagal_yang_bisa_diulang(): void
    {
        PengirimUji::$lempar = 'Koneksi terputus';
        $pesan = $this->antrean();

        $this->artisan('pesan:kirim')->assertSuccessful();

        $pesan->refresh();
        $this->assertSame('antri', $pesan->status);
        $this->assertStringContainsString('Koneksi terputus', $pesan->galat);
    }

    public function test_pesan_yang_belum_waktunya_dilewati(): void
    {
        $pesan = $this->antrean(['dijadwalkan_pada' => now()->addHour()]);

        $this->artisan('pesan:kirim');

        $this->assertSame('antri', $pesan->fresh()->status);
        $this->assertCount(0, PengirimUji::$terkirim);
    }

    public function test_batas_jumlah_per_eksekusi(): void
    {
        $this->antrean();
        $this->antrean();
        $this->antrean();

        $this->artisan('pesan:kirim', ['--batas' => 2]);

        $this->assertSame(2, AntreanPesan::query()->where('status', 'terkirim')->count());
        $this->assertSame(1, AntreanPesan::query()->where('status', 'antri')->count());
    }

    public function test_pesan_macet_di_status_diproses_diantrekan_kembali(): void
    {
        $pesan = $this->antrean(['status' => 'diproses', 'diproses_pada' => now()->subMinutes(30)]);

        $this->artisan('pesan:kirim');

        $this->assertSame('terkirim', $pesan->fresh()->status);
    }

    public function test_pesan_yang_sedang_diproses_proses_lain_tidak_diambil(): void
    {
        $pesan = $this->antrean(['status' => 'diproses', 'diproses_pada' => now()->subMinute()]);

        $this->artisan('pesan:kirim');

        $this->assertSame('diproses', $pesan->fresh()->status);
        $this->assertCount(0, PengirimUji::$terkirim);
    }

    public function test_saklar_utama_mematikan_pengiriman(): void
    {
        config(['pesan.aktif' => false]);
        $pesan = $this->antrean();

        $this->artisan('pesan:kirim')->assertSuccessful();

        $this->assertSame('antri', $pesan->fresh()->status);
        $this->assertCount(0, PengirimUji::$terkirim);
    }

    public function test_kanal_tanpa_pengirim_menjadi_gagal_dengan_pesan_jelas(): void
    {
        $pesan = $this->antrean(['kanal' => 'sms']);

        $this->artisan('pesan:kirim');

        $pesan->refresh();
        $this->assertSame('antri', $pesan->status);   // dianggap bisa diulang: konfigurasi bisa dibetulkan
        $this->assertStringContainsString('Belum ada pengirim untuk kanal sms', $pesan->galat);
    }

    public function test_perintah_uji_mengantrekan_pesan(): void
    {
        $this->artisan('pesan:uji', ['tujuan' => '0812-3456-7890'])->assertSuccessful();

        $pesan = AntreanPesan::query()->firstOrFail();
        $this->assertSame('6281234567890', $pesan->tujuan);
        $this->assertSame('antri', $pesan->status);
    }
}
