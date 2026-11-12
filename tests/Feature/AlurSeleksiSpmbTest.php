<?php

namespace Tests\Feature;

use App\Exceptions\Spmb\TransisiTidakValid;
use App\Models\Orang;
use App\Models\Siswa;
use App\Models\SpmbPendaftar;
use App\Models\User;
use App\Services\Spmb\AlurSeleksiSpmb;
use App\Services\Spmb\KonversiPendaftar;
use App\Services\Spmb\PendaftaranSpmb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class AlurSeleksiSpmbTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    private AlurSeleksiSpmb $alur;

    private int $penggunaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        $this->alur = app(AlurSeleksiSpmb::class);

        $orang = Orang::create(['nama_lengkap' => 'Panitia', 'jenis_kelamin' => 'L']);
        $this->penggunaId = User::create(['orang_id' => $orang->id, 'username' => 'panitia', 'password' => 'rahasia-uji-123'])->id;
    }

    private function daftar(array $s, array $ubah = []): SpmbPendaftar
    {
        return app(PendaftaranSpmb::class)->daftar($s['periode'], $this->dataPendaftaran($s, $ubah));
    }

    private function berkasValid(SpmbPendaftar $p, array $s): void
    {
        foreach (['kk', 'akta'] as $kunci) {
            DB::table('spmb_berkas')->insert([
                'pendaftar_id' => $p->id, 'persyaratan_id' => $s[$kunci], 'periode_id' => $p->periode_id,
                'status_verifikasi' => 'valid', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** @return array{0:int,1:int} id tahap akademik (bobot 60) dan wawancara (bobot 40) */
    private function buatTahap(array $s): array
    {
        $waktu = ['created_at' => now(), 'updated_at' => now()];
        $akademik = DB::table('spmb_tahap_seleksi')->insertGetId(['periode_id' => $s['periode']->id, 'nama' => 'Tes akademik', 'urutan' => 1, 'bobot' => 60] + $waktu);
        $wawancara = DB::table('spmb_tahap_seleksi')->insertGetId(['periode_id' => $s['periode']->id, 'nama' => 'Wawancara', 'urutan' => 2, 'bobot' => 40] + $waktu);

        return [$akademik, $wawancara];
    }

    public function test_verifikasi_ditolak_bila_berkas_wajib_belum_valid(): void
    {
        $s = $this->periodeTerbuka();
        $p = $this->daftar($s);

        $this->expectException(TransisiTidakValid::class);
        $this->alur->verifikasi($p);
    }

    public function test_verifikasi_berhasil_bila_semua_berkas_wajib_valid(): void
    {
        $s = $this->periodeTerbuka();
        $p = $this->daftar($s);
        $this->berkasValid($p, $s);

        $this->alur->verifikasi($p);

        $this->assertSame('diverifikasi', $p->fresh()->status);
    }

    public function test_nilai_belum_boleh_dicatat_sebelum_verifikasi(): void
    {
        $s = $this->periodeTerbuka();
        [$akademik] = $this->buatTahap($s);
        $p = $this->daftar($s);

        $this->expectException(TransisiTidakValid::class);
        $this->alur->catatNilai($p, $akademik, 80);
    }

    public function test_skor_dihitung_berbobot_dan_status_menjadi_seleksi(): void
    {
        $s = $this->periodeTerbuka();
        [$akademik, $wawancara] = $this->buatTahap($s);
        $p = $this->daftar($s);
        $this->berkasValid($p, $s);
        $this->alur->verifikasi($p);

        $this->alur->catatNilai($p, $akademik, 80);
        $this->assertSame('seleksi', $p->fresh()->status);

        $this->alur->catatNilai($p, $wawancara, 90);
        $this->assertSame(84.0, $this->alur->hitungSkor($p));   // (80*60 + 90*40) / 100

        // Mengisi ulang nilai pada tahap yang sama memperbarui, bukan menambah baris
        $this->alur->catatNilai($p, $akademik, 90);
        $this->assertSame(2, DB::table('spmb_nilai_seleksi')->where('pendaftar_id', $p->id)->count());
        $this->assertSame(90.0, $this->alur->hitungSkor($p));
    }

    public function test_tahap_dari_periode_lain_ditolak(): void
    {
        $a = $this->periodeTerbuka();
        $b = $this->periodeTerbuka([], 'FTN-02');
        [$tahapB] = $this->buatTahap($b);
        $p = $this->daftar($a);
        $this->berkasValid($p, $a);
        $this->alur->verifikasi($p);

        $this->expectException(TransisiTidakValid::class);
        $this->alur->catatNilai($p, $tahapB, 80);
    }

    public function test_hasil_dan_peringkat(): void
    {
        $s = $this->periodeTerbuka();
        [$akademik] = $this->buatTahap($s);

        $tinggi = $this->daftar($s);
        $rendah = $this->daftar($s, ['nama_lengkap' => 'Umar Putra', 'tanggal_lahir' => '2018-05-05', 'nik' => '1671012345678902']);

        foreach ([[$tinggi, 90], [$rendah, 80]] as [$p, $nilai]) {
            $this->berkasValid($p, $s);
            $this->alur->verifikasi($p);
            $this->alur->catatNilai($p, $akademik, $nilai);
            $this->alur->tetapkanHasil($p, 'lulus', $this->penggunaId);
        }

        $this->assertSame(2, $this->alur->hitungPeringkat($s['periode']->id));
        $this->assertSame(1, (int) DB::table('spmb_hasil')->where('pendaftar_id', $tinggi->id)->value('peringkat'));
        $this->assertSame(2, (int) DB::table('spmb_hasil')->where('pendaftar_id', $rendah->id)->value('peringkat'));
        $this->assertSame('lulus', $tinggi->fresh()->status);
    }

    public function test_keputusan_tidak_valid_ditolak(): void
    {
        $s = $this->periodeTerbuka();
        $p = $this->daftar($s);
        $this->berkasValid($p, $s);
        $this->alur->verifikasi($p);

        $this->expectException(TransisiTidakValid::class);
        $this->alur->tetapkanHasil($p, 'mungkin', $this->penggunaId);
    }

    public function test_daftar_ulang_hanya_untuk_yang_lulus(): void
    {
        $s = $this->periodeTerbuka();
        $p = $this->daftar($s);

        $this->expectException(TransisiTidakValid::class);
        $this->alur->mulaiDaftarUlang($p);
    }

    public function test_alur_lengkap_dari_pendaftaran_sampai_menjadi_siswa(): void
    {
        $s = $this->periodeTerbuka();
        [$akademik] = $this->buatTahap($s);
        $rombel = DB::table('rombel')->insertGetId([
            'sekolah_id' => $s['sekolah'], 'cabang_id' => $s['cabang'], 'tahun_ajaran_id' => $s['tahun'],
            'tingkat_id' => DB::table('tingkat')->where('kode', '1')->value('id'),
            'nama' => '1 Abu Bakar', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $p = $this->daftar($s);
        $this->berkasValid($p, $s);
        $this->alur->verifikasi($p);
        $this->alur->catatNilai($p, $akademik, 85);
        $this->alur->tetapkanHasil($p, 'lulus', $this->penggunaId);
        $this->alur->mulaiDaftarUlang($p->fresh());
        $this->alur->selesaikanDaftarUlang($p->fresh(), $rombel);

        $siswa = app(KonversiPendaftar::class)->konversi($p->fresh());

        $this->assertInstanceOf(Siswa::class, $siswa);
        $this->assertSame('menjadi_siswa', $p->fresh()->status);
        $this->assertSame($rombel, (int) DB::table('rombel_siswa')->where('siswa_id', $siswa->id)->value('rombel_id'));
    }

    public function test_rombel_milik_sekolah_lain_ditolak_saat_daftar_ulang(): void
    {
        $a = $this->periodeTerbuka();
        $b = $this->periodeTerbuka([], 'FTN-02');
        $rombelB = DB::table('rombel')->insertGetId([
            'sekolah_id' => $b['sekolah'], 'cabang_id' => $b['cabang'], 'tahun_ajaran_id' => $b['tahun'],
            'tingkat_id' => DB::table('tingkat')->where('kode', '1')->value('id'),
            'nama' => '1 Umar', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $p = $this->daftar($a);
        $p->update(['status' => 'daftar_ulang']);

        $this->expectException(TransisiTidakValid::class);
        $this->alur->selesaikanDaftarUlang($p, $rombelB);
    }

    public function test_mundur_menandai_pendaftar_dan_kontak_serta_tidak_bisa_diulang(): void
    {
        $s = $this->periodeTerbuka();
        $p = $this->daftar($s);

        $this->alur->mundur($p, 'Pindah kota');

        $this->assertSame('mundur', $p->fresh()->status);
        $this->assertSame('tidak_lanjut', DB::table('spmb_kontak')->where('id', $p->kontak_id)->value('status'));

        $this->expectException(TransisiTidakValid::class);
        $this->alur->mundur($p->fresh());
    }
}
