<?php

namespace Tests\Feature;

use App\Exceptions\Spmb\TransisiTidakValid;
use App\Models\AntreanPesan;
use App\Models\SpmbBerkas;
use App\Models\SpmbPendaftar;
use App\Services\Spmb\AlurSeleksiSpmb;
use App\Services\Spmb\PendaftaranSpmb;
use App\Services\Spmb\PerbaikanBerkasSpmb;
use App\Support\Telepon;
use Database\Seeders\TemplatPesanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

/**
 * Pesan otomatis ke kontak utama pada peristiwa SPMB. Bila tes pertama gagal karena tidak ada pesan sama sekali,
 * listener App\Listeners\Spmb\AntrekanNotifikasiSpmb tidak ditemukan otomatis oleh aplikasi Anda: daftarkan dengan
 * Event::listen di AksesServiceProvider.
 */
class NotifikasiSpmbTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        $this->seed(TemplatPesanSeeder::class);
        Storage::fake('local');
        config(['pesan.aktif' => true, 'pesan.jam_tenang' => ['mulai' => null, 'selesai' => null]]);
    }

    /** @return array{0: SpmbPendaftar, 1: SpmbBerkas, 2: array} */
    private function pendaftar(): array
    {
        $s = $this->periodeTerbuka();
        $p = app(PendaftaranSpmb::class)->daftar($s['periode'], $this->dataPendaftaran($s), [
            $s['kk'] => UploadedFile::fake()->image('kk.jpg'),
            $s['akta'] => UploadedFile::fake()->create('akta.pdf', 100, 'application/pdf'),
        ]);
        $kk = SpmbBerkas::query()->where('pendaftar_id', $p->id)->where('persyaratan_id', $s['kk'])->firstOrFail();

        return [$p, $kk, $s];
    }

    public function test_pendaftaran_mengantrekan_pesan_konfirmasi_ke_kontak_utama(): void
    {
        [$p] = $this->pendaftar();

        $pesan = AntreanPesan::query()->where('kunci_unik', "spmb_terdaftar:{$p->id}")->first();

        $this->assertNotNull($pesan, 'Pesan konfirmasi tidak diantrekan: periksa penemuan otomatis listener.');
        $this->assertSame(Telepon::normal('0813-3333-4444'), $pesan->tujuan);   // kontak utama = ibu
        $this->assertStringContainsString($p->nomor_pendaftaran, $pesan->isi);
        $this->assertStringContainsString('Siti Aminah', $pesan->isi);
        $this->assertSame($p->cabang_id, (int) $pesan->cabang_id);
    }

    public function test_pendaftaran_tetap_berhasil_bila_templat_tidak_ada(): void
    {
        DB::table('templat_pesan')->delete();

        [$p] = $this->pendaftar();

        $this->assertSame('terdaftar', $p->status);
        $this->assertSame(0, AntreanPesan::query()->count());
    }

    public function test_pesan_nonaktif_tidak_dibuat(): void
    {
        config(['pesan.aktif' => false]);

        $this->pendaftar();

        $this->assertSame(0, AntreanPesan::query()->count());
    }

    public function test_berkas_ditolak_mengantrekan_pesan_berisi_alasan(): void
    {
        [, $kk] = $this->pendaftar();

        app(AlurSeleksiSpmb::class)->tetapkanBerkas($kk, 'tidak_valid', 'Foto buram', null);

        $pesan = AntreanPesan::query()->where('kunci_unik', 'like', 'spmb_berkas_ditolak:%')->firstOrFail();
        $this->assertStringContainsString('Foto buram', $pesan->isi);
        $this->assertStringContainsString('Kartu Keluarga', $pesan->isi);
    }

    public function test_penolakan_yang_sama_tidak_mengirim_pesan_ganda_tetapi_penolakan_setelah_unggah_ulang_ya(): void
    {
        [, $kk] = $this->pendaftar();
        $alur = app(AlurSeleksiSpmb::class);

        $alur->tetapkanBerkas($kk, 'tidak_valid', 'Foto buram', null);
        $alur->tetapkanBerkas($kk->fresh(), 'tidak_valid', 'Foto buram', null);
        $this->assertSame(1, AntreanPesan::query()->where('kunci_unik', 'like', 'spmb_berkas_ditolak:%')->count());

        app(PerbaikanBerkasSpmb::class)->ganti($kk->fresh(), UploadedFile::fake()->image('kk-baru.jpg'));
        $alur->tetapkanBerkas($kk->fresh(), 'tidak_valid', 'Masih buram', null);

        $this->assertSame(2, AntreanPesan::query()->where('kunci_unik', 'like', 'spmb_berkas_ditolak:%')->count());
    }

    public function test_alasan_penolakan_wajib(): void
    {
        [, $kk] = $this->pendaftar();

        $this->expectException(TransisiTidakValid::class);
        app(AlurSeleksiSpmb::class)->tetapkanBerkas($kk, 'tidak_valid', '', null);
    }

    public function test_berkas_valid_tidak_mengirim_pesan(): void
    {
        [, $kk] = $this->pendaftar();
        $sebelum = AntreanPesan::query()->count();

        app(AlurSeleksiSpmb::class)->tetapkanBerkas($kk, 'valid', null, null);

        $this->assertSame($sebelum, AntreanPesan::query()->count());
        $this->assertSame('valid', $kk->fresh()->status_verifikasi);
    }

    public function test_verifikasi_mengantrekan_pesan(): void
    {
        [$p, , $s] = $this->pendaftar();
        DB::table('spmb_berkas')->where('pendaftar_id', $p->id)->update(['status_verifikasi' => 'valid']);

        app(AlurSeleksiSpmb::class)->verifikasi($p->fresh());

        $this->assertNotNull(AntreanPesan::query()->where('kunci_unik', "spmb_terverifikasi:{$p->id}")->first());
    }
}
