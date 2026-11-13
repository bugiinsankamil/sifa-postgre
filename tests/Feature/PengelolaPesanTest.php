<?php

namespace Tests\Feature;

use App\Models\AntreanPesan;
use App\Models\TemplatPesan;
use App\Pesan\JamTenang;
use App\Services\Pesan\PengelolaPesan;
use Database\Seeders\TemplatPesanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class PengelolaPesanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TemplatPesanSeeder::class);
        config(['pesan.aktif' => true, 'pesan.jam_tenang' => ['mulai' => null, 'selesai' => null]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function data(): array
    {
        return [
            'nama_wali' => 'Siti Aminah', 'nama_anak' => 'Aisyah Putri', 'sekolah' => 'SDIT Fathona',
            'nomor' => 'SPMB/FTN-01/2026/0001', 'url_status' => 'https://contoh.test/spmb/status',
        ];
    }

    public function test_antri_menyusun_isi_dan_menormalkan_nomor(): void
    {
        $pesan = app(PengelolaPesan::class)->antri('spmb_terdaftar', '0812-3456-7890', $this->data());

        $this->assertSame('6281234567890', $pesan->tujuan);
        $this->assertSame('antri', $pesan->status);
        $this->assertStringContainsString('Aisyah Putri', $pesan->isi);
        $this->assertStringContainsString('SPMB/FTN-01/2026/0001', $pesan->isi);
        $this->assertStringNotContainsString('{{', $pesan->isi);
    }

    public function test_kunci_unik_mencegah_pesan_ganda(): void
    {
        $pengelola = app(PengelolaPesan::class);

        $a = $pengelola->antri('spmb_terdaftar', '081234567890', $this->data(), ['kunci_unik' => 'uji:1']);
        $b = $pengelola->antri('spmb_terdaftar', '081234567890', $this->data(), ['kunci_unik' => 'uji:1']);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, AntreanPesan::query()->count());
    }

    public function test_templat_tidak_ada_atau_nonaktif_dilewati_tanpa_galat(): void
    {
        $pengelola = app(PengelolaPesan::class);

        $this->assertNull($pengelola->antri('tidak_ada', '081234567890', $this->data()));

        TemplatPesan::query()->where('kode', 'spmb_terdaftar')->update(['is_aktif' => false]);
        $this->assertNull($pengelola->antri('spmb_terdaftar', '081234567890', $this->data()));

        $this->assertSame(0, AntreanPesan::query()->count());
    }

    public function test_variabel_yang_kurang_dilaporkan(): void
    {
        $data = $this->data();
        unset($data['nomor']);

        $this->expectException(InvalidArgumentException::class);
        app(PengelolaPesan::class)->antri('spmb_terdaftar', '081234567890', $data);
    }

    public function test_saklar_utama_mematikan_antrean(): void
    {
        config(['pesan.aktif' => false]);

        $this->assertNull(app(PengelolaPesan::class)->antri('spmb_terdaftar', '081234567890', $this->data()));
        $this->assertSame(0, AntreanPesan::query()->count());
    }

    public function test_pesan_di_jam_tenang_diundur_ke_pagi(): void
    {
        config(['pesan.jam_tenang' => ['mulai' => '21:00', 'selesai' => '06:00']]);
        $zona = config('app.timezone');
        Carbon::setTestNow(Carbon::create(2026, 11, 12, 22, 30, 0, $zona));

        $pesan = app(PengelolaPesan::class)->antri('spmb_terdaftar', '081234567890', $this->data());

        $this->assertSame('2026-11-13 06:00', $pesan->dijadwalkan_pada->format('Y-m-d H:i'));
    }

    public function test_jam_tenang_untuk_berbagai_waktu(): void
    {
        config(['pesan.jam_tenang' => ['mulai' => '21:00', 'selesai' => '06:00']]);

        $this->assertSame('2026-11-13 06:00', JamTenang::sesuaikan(Carbon::create(2026, 11, 12, 22, 30))->format('Y-m-d H:i'));
        $this->assertSame('2026-11-12 06:00', JamTenang::sesuaikan(Carbon::create(2026, 11, 12, 2, 0))->format('Y-m-d H:i'));
        $this->assertSame('2026-11-12 12:00', JamTenang::sesuaikan(Carbon::create(2026, 11, 12, 12, 0))->format('Y-m-d H:i'));

        config(['pesan.jam_tenang' => ['mulai' => '13:00', 'selesai' => '15:00']]);
        $this->assertSame('2026-11-12 15:00', JamTenang::sesuaikan(Carbon::create(2026, 11, 12, 14, 0))->format('Y-m-d H:i'));
        $this->assertSame('2026-11-12 16:00', JamTenang::sesuaikan(Carbon::create(2026, 11, 12, 16, 0))->format('Y-m-d H:i'));

        config(['pesan.jam_tenang' => ['mulai' => null, 'selesai' => null]]);
        $this->assertSame('2026-11-12 23:30', JamTenang::sesuaikan(Carbon::create(2026, 11, 12, 23, 30))->format('Y-m-d H:i'));
    }
}
