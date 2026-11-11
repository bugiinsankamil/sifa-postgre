<?php

namespace Tests\Feature;

use App\Models\SpmbKontak;
use App\Support\Telepon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class PendaftaranSpmbTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        Storage::fake('local');
    }

    private function kirim(array $s, array $ubah = [], bool $denganBerkas = true)
    {
        $data = $this->dataPendaftaran($s, $ubah) + ['persetujuan' => '1'];

        if ($denganBerkas) {
            $data['berkas'] = [
                $s['kk'] => UploadedFile::fake()->image('kk.jpg'),
                $s['akta'] => UploadedFile::fake()->create('akta.pdf', 200, 'application/pdf'),
            ];
        }

        return $this->post(route('spmb.store', $s['periode']->id), $data);
    }

    public function test_daftar_pendaftaran_yang_dibuka_menampilkan_form(): void
    {
        $s = $this->periodeTerbuka();

        $this->get(route('spmb.form', $s['periode']->id))
            ->assertOk()
            ->assertSee('Kartu Keluarga')
            ->assertSee('Reguler');
    }

    public function test_beranda_menampilkan_periode_yang_dibuka(): void
    {
        $this->periodeTerbuka();

        $this->get(route('spmb.index'))->assertOk()->assertSee('SDIT Fathona Palembang');
    }

    public function test_periode_ditutup_menampilkan_pesan_dan_menolak_pendaftaran(): void
    {
        $s = $this->periodeTerbuka(['status' => 'ditutup']);

        $this->get(route('spmb.form', $s['periode']->id))->assertOk()->assertSee('sedang tidak dibuka');

        $this->kirim($s)->assertSessionHasErrors('umum');
        $this->assertSame(0, DB::table('spmb_pendaftar')->count());
    }

    public function test_pendaftaran_menyimpan_pendaftar_wali_berkas_dan_kontak(): void
    {
        $s = $this->periodeTerbuka();

        $this->kirim($s)
            ->assertRedirect(route('spmb.terdaftar'))
            ->assertSessionHas('nomor');

        $pendaftar = DB::table('spmb_pendaftar')->first();
        $this->assertSame('terdaftar', $pendaftar->status);
        $this->assertStringStartsWith('SPMB/FTN-01/', $pendaftar->nomor_pendaftaran);
        $this->assertSame(2, DB::table('spmb_pendaftar_wali')->where('pendaftar_id', $pendaftar->id)->count());
        $this->assertSame(2, DB::table('spmb_berkas')->where('pendaftar_id', $pendaftar->id)->count());

        $lampiran = DB::table('lampiran')->get();
        $this->assertCount(2, $lampiran);
        Storage::disk('local')->assertExists($lampiran->first()->path);

        $kontak = DB::table('spmb_kontak')->first();
        $this->assertSame($kontak->id, $pendaftar->kontak_id);
        $this->assertSame('mendaftar', $kontak->status);
        $this->assertSame(Telepon::normal('0813-3333-4444'), $kontak->telepon);
    }

    public function test_pendaftaran_memakai_kontak_yang_sudah_ada(): void
    {
        $s = $this->periodeTerbuka();
        $ada = SpmbKontak::create([
            'cabang_id' => $s['cabang'], 'tahun_ajaran_id' => $s['tahun'], 'nama_kontak' => 'Ibu Siti',
            'telepon' => '0813-3333-4444', 'nama_calon_siswa' => 'Aisyah Putri', 'sumber_ref' => 'instagram', 'status' => 'tertarik',
        ]);

        $this->kirim($s)->assertRedirect(route('spmb.terdaftar'));

        $this->assertSame(1, SpmbKontak::query()->count());
        $this->assertSame('mendaftar', $ada->fresh()->status);
        $this->assertSame($ada->id, DB::table('spmb_pendaftar')->value('kontak_id'));
    }

    public function test_pendaftar_ganda_ditolak(): void
    {
        $s = $this->periodeTerbuka();

        $this->kirim($s)->assertRedirect(route('spmb.terdaftar'));
        $this->kirim($s)->assertSessionHasErrors('umum');

        $this->assertSame(1, DB::table('spmb_pendaftar')->count());
    }

    public function test_kakak_adik_dengan_nomor_orang_tua_sama_diterima(): void
    {
        $s = $this->periodeTerbuka();

        $this->kirim($s)->assertRedirect(route('spmb.terdaftar'));
        $this->kirim($s, ['nama_lengkap' => 'Umar Putra', 'tanggal_lahir' => '2018-05-05', 'nik' => '1671012345678902'])
            ->assertRedirect(route('spmb.terdaftar'));

        $this->assertSame(2, DB::table('spmb_pendaftar')->count());
        $this->assertSame(2, DB::table('spmb_kontak')->count());
    }

    public function test_validasi_berkas_wajib_dan_data_wali(): void
    {
        $s = $this->periodeTerbuka();

        $this->kirim($s, ['wali' => ['ayah' => ['nama' => ''], 'ibu' => ['nama' => '']]], false)
            ->assertSessionHasErrors(["berkas.{$s['kk']}", "berkas.{$s['akta']}", 'wali.ayah.nama']);

        $this->assertSame(0, DB::table('spmb_pendaftar')->count());
    }

    public function test_kontak_utama_wajib_memiliki_telepon(): void
    {
        $s = $this->periodeTerbuka();

        $this->kirim($s, ['wali' => ['ibu' => ['telepon' => '']]])->assertSessionHasErrors('wali.ibu.telepon');
    }

    public function test_jebakan_bot_yang_terisi_ditolak(): void
    {
        $s = $this->periodeTerbuka();

        $data = $this->dataPendaftaran($s) + ['persetujuan' => '1', 'situs' => 'http://spam.example'];
        $data['berkas'] = [
            $s['kk'] => UploadedFile::fake()->image('kk.jpg'),
            $s['akta'] => UploadedFile::fake()->image('akta.jpg'),
        ];

        $this->post(route('spmb.store', $s['periode']->id), $data)->assertSessionHasErrors('situs');
        $this->assertSame(0, DB::table('spmb_pendaftar')->count());
    }

    public function test_cek_status_dengan_nomor_dan_tanggal_lahir(): void
    {
        $s = $this->periodeTerbuka();
        $this->kirim($s);
        $nomor = DB::table('spmb_pendaftar')->value('nomor_pendaftaran');

        $this->post(route('spmb.status.cek'), ['nomor_pendaftaran' => $nomor, 'tanggal_lahir' => '2020-03-15'])
            ->assertOk()
            ->assertSee('Aisyah Putri')
            ->assertSee('menunggu verifikasi');

        $this->post(route('spmb.status.cek'), ['nomor_pendaftaran' => $nomor, 'tanggal_lahir' => '2019-01-01'])
            ->assertOk()
            ->assertSee('Data tidak ditemukan');
    }

    public function test_hasil_seleksi_disembunyikan_di_cek_status_sampai_diizinkan(): void
    {
        $s = $this->periodeTerbuka();
        $this->kirim($s);
        DB::table('spmb_pendaftar')->update(['status' => 'lulus']);
        $nomor = DB::table('spmb_pendaftar')->value('nomor_pendaftaran');
        $data = ['nomor_pendaftaran' => $nomor, 'tanggal_lahir' => '2020-03-15'];

        $this->post(route('spmb.status.cek'), $data)->assertSee('akan diumumkan')->assertDontSee('Dinyatakan lulus');

        config(['spmb.tampilkan_hasil_di_status' => true]);
        $this->post(route('spmb.status.cek'), $data)->assertSee('Dinyatakan lulus');
    }
}
