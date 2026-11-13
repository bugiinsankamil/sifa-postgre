<?php

namespace Tests\Feature;

use App\Models\Lampiran;
use App\Models\SpmbBerkas;
use App\Models\SpmbPendaftar;
use App\Services\Spmb\PendaftaranSpmb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class PerbaikanBerkasSpmbTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        Storage::fake('local');
    }

    /** @return array{0: SpmbPendaftar, 1: SpmbBerkas} pendaftar dan berkas Kartu Keluarga-nya */
    private function pendaftarDenganBerkas(array $s, array $ubah = []): array
    {
        $p = app(PendaftaranSpmb::class)->daftar($s['periode'], $this->dataPendaftaran($s, $ubah), [
            $s['kk'] => UploadedFile::fake()->image('kk.jpg'),
            $s['akta'] => UploadedFile::fake()->create('akta.pdf', 100, 'application/pdf'),
        ]);

        $kk = SpmbBerkas::query()->where('pendaftar_id', $p->id)->where('persyaratan_id', $s['kk'])->firstOrFail();

        return [$p, $kk];
    }

    private function tolak(SpmbBerkas $berkas): void
    {
        $berkas->update(['status_verifikasi' => 'tidak_valid', 'catatan' => 'Foto buram']);
    }

    private function sesi(SpmbPendaftar $p, ?int $waktu = null): array
    {
        return ['spmb.pendaftar_id' => $p->id, 'spmb.waktu' => $waktu ?? now()->timestamp];
    }

    private function jumlahLampiran(SpmbBerkas $berkas, bool $denganTerhapus = false): int
    {
        $query = $denganTerhapus ? Lampiran::withTrashed() : Lampiran::query();

        return $query->where('lampiran_type', $berkas->getMorphClass())->where('lampiran_id', $berkas->id)->count();
    }

    public function test_form_unggah_tampil_untuk_berkas_yang_ditolak(): void
    {
        $s = $this->periodeTerbuka();
        [$p, $kk] = $this->pendaftarDenganBerkas($s);
        $this->tolak($kk);

        $this->post(route('spmb.status.cek'), ['nomor_pendaftaran' => $p->nomor_pendaftaran, 'tanggal_lahir' => '2020-03-15'])
            ->assertOk()
            ->assertSee('Perlu diperbaiki')
            ->assertSee('Foto buram')
            ->assertSee('Unggah perbaikan');
    }

    public function test_form_unggah_tidak_tampil_bila_tidak_ada_berkas_ditolak(): void
    {
        $s = $this->periodeTerbuka();
        [$p] = $this->pendaftarDenganBerkas($s);

        $this->post(route('spmb.status.cek'), ['nomor_pendaftaran' => $p->nomor_pendaftaran, 'tanggal_lahir' => '2020-03-15'])
            ->assertOk()
            ->assertDontSee('Unggah perbaikan');
    }

    public function test_unggah_ulang_mengganti_berkas_dan_mengembalikan_status_menunggu(): void
    {
        $s = $this->periodeTerbuka();
        [$p, $kk] = $this->pendaftarDenganBerkas($s);
        $this->tolak($kk);

        $this->withSession($this->sesi($p))
            ->post(route('spmb.berkas.unggah', $kk->id), ['berkas' => UploadedFile::fake()->image('kk-baru.jpg')])
            ->assertRedirect(route('spmb.status.saya'))
            ->assertSessionHas('sukses');

        $kk->refresh();
        $this->assertSame('menunggu', $kk->status_verifikasi);
        $this->assertNull($kk->catatan);

        // Berkas lama diarsipkan (soft delete), berkas baru aktif
        $this->assertSame(2, $this->jumlahLampiran($kk, denganTerhapus: true));
        $this->assertSame(1, $this->jumlahLampiran($kk));
        Storage::disk('local')->assertExists($kk->lampiran()->first()->path);
    }

    public function test_tanpa_sesi_unggah_ulang_ditolak(): void
    {
        $s = $this->periodeTerbuka();
        [, $kk] = $this->pendaftarDenganBerkas($s);
        $this->tolak($kk);

        $this->post(route('spmb.berkas.unggah', $kk->id), ['berkas' => UploadedFile::fake()->image('kk-baru.jpg')])
            ->assertRedirect(route('spmb.status'));

        $this->assertSame('tidak_valid', $kk->fresh()->status_verifikasi);
    }

    public function test_sesi_kedaluwarsa_ditolak(): void
    {
        $s = $this->periodeTerbuka();
        [$p, $kk] = $this->pendaftarDenganBerkas($s);
        $this->tolak($kk);

        $this->withSession($this->sesi($p, now()->subMinutes(31)->timestamp))
            ->post(route('spmb.berkas.unggah', $kk->id), ['berkas' => UploadedFile::fake()->image('kk-baru.jpg')])
            ->assertRedirect(route('spmb.status'));

        $this->assertSame('tidak_valid', $kk->fresh()->status_verifikasi);
    }

    public function test_berkas_milik_pendaftar_lain_tidak_bisa_diganti(): void
    {
        $s = $this->periodeTerbuka();
        [$pertama] = $this->pendaftarDenganBerkas($s);
        [, $kkKedua] = $this->pendaftarDenganBerkas($s, ['nama_lengkap' => 'Umar Putra', 'tanggal_lahir' => '2018-05-05', 'nik' => '1671012345678902']);
        $this->tolak($kkKedua);

        $this->withSession($this->sesi($pertama))
            ->post(route('spmb.berkas.unggah', $kkKedua->id), ['berkas' => UploadedFile::fake()->image('kk-baru.jpg')])
            ->assertForbidden();

        $this->assertSame('tidak_valid', $kkKedua->fresh()->status_verifikasi);
    }

    public function test_berkas_yang_belum_ditolak_tidak_bisa_diganti(): void
    {
        $s = $this->periodeTerbuka();
        [$p, $kk] = $this->pendaftarDenganBerkas($s);

        $this->withSession($this->sesi($p))
            ->post(route('spmb.berkas.unggah', $kk->id), ['berkas' => UploadedFile::fake()->image('kk-baru.jpg')])
            ->assertSessionHasErrors('umum');

        $this->assertSame(1, $this->jumlahLampiran($kk, denganTerhapus: true));
    }

    public function test_unggah_ulang_ditutup_setelah_verifikasi_selesai(): void
    {
        $s = $this->periodeTerbuka();
        [$p, $kk] = $this->pendaftarDenganBerkas($s);
        $this->tolak($kk);
        $p->update(['status' => 'diverifikasi']);

        $this->withSession($this->sesi($p))
            ->post(route('spmb.berkas.unggah', $kk->id), ['berkas' => UploadedFile::fake()->image('kk-baru.jpg')])
            ->assertSessionHasErrors('umum');

        $this->assertSame('tidak_valid', $kk->fresh()->status_verifikasi);
    }

    public function test_format_berkas_yang_tidak_diizinkan_ditolak(): void
    {
        $s = $this->periodeTerbuka();
        [$p, $kk] = $this->pendaftarDenganBerkas($s);
        $this->tolak($kk);

        $this->withSession($this->sesi($p))
            ->post(route('spmb.berkas.unggah', $kk->id), ['berkas' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertSessionHasErrors('berkas');

        $this->assertSame('tidak_valid', $kk->fresh()->status_verifikasi);
        $this->assertSame(1, $this->jumlahLampiran($kk, denganTerhapus: true));
    }

    public function test_status_saya_tanpa_sesi_dialihkan_ke_form_cek_status(): void
    {
        $this->get(route('spmb.status.saya'))->assertRedirect(route('spmb.status'));
    }
}
