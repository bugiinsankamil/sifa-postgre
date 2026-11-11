<?php

namespace Tests\Feature;

use App\Exceptions\Spmb\KonversiTidakBisa;
use App\Models\Orang;
use App\Models\Siswa;
use App\Models\SpmbPendaftar;
use App\Models\SpmbPeriode;
use App\Models\User;
use App\Services\Spmb\KonversiPendaftar;
use App\Services\Spmb\PendaftaranSpmb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class KonversiPendaftarTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    private int $penggunaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();

        $orang = Orang::create(['nama_lengkap' => 'Panitia', 'jenis_kelamin' => 'L']);
        $this->penggunaId = User::create(['orang_id' => $orang->id, 'username' => 'panitia', 'password' => 'rahasia-uji-123'])->id;
    }

    private function daftar(array $s, array $ubah = [], ?SpmbPeriode $periode = null): SpmbPendaftar
    {
        return app(PendaftaranSpmb::class)->daftar($periode ?? $s['periode'], $this->dataPendaftaran($s, $ubah));
    }

    private function luluskan(SpmbPendaftar $p, string $keputusan = 'lulus', string $daftarUlang = 'selesai', ?int $rombelId = null): void
    {
        $waktu = ['created_at' => now(), 'updated_at' => now()];

        DB::table('spmb_hasil')->insert([
            'pendaftar_id' => $p->id, 'keputusan' => $keputusan, 'tanggal_keputusan' => today()->toDateString(),
            'ditetapkan_oleh' => $this->penggunaId,
        ] + $waktu);
        DB::table('spmb_daftar_ulang')->insert([
            'pendaftar_id' => $p->id, 'status' => $daftarUlang, 'tanggal' => today()->toDateString(), 'rombel_id' => $rombelId,
        ] + $waktu);
    }

    /** Jumlah orang selain akun panitia yang dibuat di setUp */
    private function jumlahOrang(): int
    {
        return Orang::query()->where('nama_lengkap', '!=', 'Panitia')->count();
    }

    private function buatRombel(array $s): int
    {
        return DB::table('rombel')->insertGetId([
            'sekolah_id' => $s['sekolah'], 'cabang_id' => $s['cabang'], 'tahun_ajaran_id' => $s['tahun'],
            'tingkat_id' => DB::table('tingkat')->where('kode', '1')->value('id'),
            'nama' => '1 Abu Bakar', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_konversi_membuat_siswa_wali_keanggotaan_dan_rombel(): void
    {
        $s = $this->periodeTerbuka();
        $rombel = $this->buatRombel($s);
        $p = $this->daftar($s);
        $this->luluskan($p, rombelId: $rombel);

        $siswa = app(KonversiPendaftar::class)->konversi($p);

        $this->assertSame(today()->year.'0001', $siswa->nis_yayasan);
        $this->assertSame(3, $this->jumlahOrang());   // anak, ayah, ibu
        $this->assertSame(2, DB::table('siswa_wali')->where('siswa_id', $siswa->id)->count());

        $keanggotaan = DB::table('siswa_sekolah')->where('siswa_id', $siswa->id)->first();
        $this->assertSame($s['sekolah'], (int) $keanggotaan->sekolah_id);
        $this->assertSame('baru', $keanggotaan->jenis_masuk);
        $this->assertNull($keanggotaan->tanggal_keluar);

        $this->assertSame($rombel, (int) DB::table('rombel_siswa')->where('siswa_id', $siswa->id)->value('rombel_id'));

        $p->refresh();
        $this->assertSame('menjadi_siswa', $p->status);
        $this->assertSame($siswa->id, (int) $p->siswa_id);
    }

    public function test_konversi_dua_kali_ditolak(): void
    {
        $s = $this->periodeTerbuka();
        $p = $this->daftar($s);
        $this->luluskan($p);

        app(KonversiPendaftar::class)->konversi($p);

        $this->expectException(KonversiTidakBisa::class);
        app(KonversiPendaftar::class)->konversi($p->fresh());
    }

    public function test_konversi_ditolak_bila_belum_lulus(): void
    {
        $s = $this->periodeTerbuka();
        $p = $this->daftar($s);
        $this->luluskan($p, keputusan: 'cadangan');

        $this->expectException(KonversiTidakBisa::class);
        app(KonversiPendaftar::class)->konversi($p);
    }

    public function test_konversi_ditolak_bila_daftar_ulang_belum_selesai(): void
    {
        $s = $this->periodeTerbuka();
        $p = $this->daftar($s);
        $this->luluskan($p, daftarUlang: 'menunggu');

        $this->expectException(KonversiTidakBisa::class);
        app(KonversiPendaftar::class)->konversi($p);
    }

    public function test_orang_tua_yang_sama_dipakai_ulang_untuk_adik(): void
    {
        $s = $this->periodeTerbuka();
        $wali = ['wali' => [
            'ayah' => ['nik' => '1671011010850001'],
            'ibu' => ['nik' => '1671015505900002'],
        ]];

        $kakak = $this->daftar($s, $wali);
        $adik = $this->daftar($s, $wali + ['nama_lengkap' => 'Umar Putra', 'tanggal_lahir' => '2018-05-05', 'nik' => '1671012345678902']);
        $this->luluskan($kakak);
        $this->luluskan($adik);

        app(KonversiPendaftar::class)->konversi($kakak);
        app(KonversiPendaftar::class)->konversi($adik);

        $this->assertSame(2, Siswa::query()->count());
        $this->assertSame(4, $this->jumlahOrang());   // 2 anak + ayah + ibu, bukan 6
        $this->assertSame(2, DB::table('siswa_wali')->distinct()->count('orang_id'));
    }

    public function test_anak_yang_naik_jenjang_memakai_siswa_yang_sama(): void
    {
        $s = $this->periodeTerbuka();
        $pertama = $this->daftar($s);
        $this->luluskan($pertama);
        $siswa = app(KonversiPendaftar::class)->konversi($pertama);

        // Keanggotaan lama ditutup, lalu anak yang sama mendaftar lagi pada periode berikutnya
        DB::table('siswa_sekolah')->where('siswa_id', $siswa->id)->update(['tanggal_keluar' => today()->toDateString(), 'jenis_keluar' => 'lulus']);
        $periode2 = SpmbPeriode::create([
            'cabang_id' => $s['cabang'], 'sekolah_id' => $s['sekolah'], 'tahun_ajaran_id' => $s['tahun'],
            'nama' => 'Gelombang 2', 'gelombang' => 2, 'status' => 'dibuka',
            'tanggal_buka' => today()->subDay()->toDateString(), 'tanggal_tutup' => today()->addDays(10)->toDateString(), 'kuota' => 10,
        ]);
        $jalur2 = DB::table('spmb_jalur')->insertGetId(['periode_id' => $periode2->id, 'nama' => 'Reguler', 'is_aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $kedua = $this->daftar($s, ['jalur_id' => $jalur2], $periode2);
        $this->luluskan($kedua);

        $siswa2 = app(KonversiPendaftar::class)->konversi($kedua);

        $this->assertSame($siswa->id, $siswa2->id);
        $this->assertSame(1, Siswa::query()->count());
        $this->assertSame(2, DB::table('siswa_sekolah')->where('siswa_id', $siswa->id)->count());
        $this->assertSame('naik_jenjang', DB::table('siswa_sekolah')->where('siswa_id', $siswa->id)->orderByDesc('id')->value('jenis_masuk'));
    }

    public function test_anak_yang_masih_aktif_di_sekolah_lain_ditolak(): void
    {
        $s = $this->periodeTerbuka();
        $pertama = $this->daftar($s);
        $this->luluskan($pertama);
        app(KonversiPendaftar::class)->konversi($pertama);

        $periode2 = SpmbPeriode::create([
            'cabang_id' => $s['cabang'], 'sekolah_id' => $s['sekolah'], 'tahun_ajaran_id' => $s['tahun'],
            'nama' => 'Gelombang 2', 'gelombang' => 2, 'status' => 'dibuka',
            'tanggal_buka' => today()->subDay()->toDateString(), 'tanggal_tutup' => today()->addDays(10)->toDateString(), 'kuota' => 10,
        ]);
        $jalur2 = DB::table('spmb_jalur')->insertGetId(['periode_id' => $periode2->id, 'nama' => 'Reguler', 'is_aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $kedua = $this->daftar($s, ['jalur_id' => $jalur2], $periode2);
        $this->luluskan($kedua);

        $this->expectException(KonversiTidakBisa::class);
        app(KonversiPendaftar::class)->konversi($kedua);
    }

    public function test_perintah_konversi_massal_mengonversi_yang_memenuhi_syarat(): void
    {
        $s = $this->periodeTerbuka();
        $layak = $this->daftar($s);
        $belum = $this->daftar($s, ['nama_lengkap' => 'Umar Putra', 'tanggal_lahir' => '2018-05-05', 'nik' => '1671012345678902']);
        $this->luluskan($layak);
        $this->luluskan($belum, keputusan: 'cadangan');

        $this->artisan('spmb:konversi', ['periode' => $s['periode']->id])->assertSuccessful();

        $this->assertSame('menjadi_siswa', $layak->fresh()->status);
        $this->assertNotSame('menjadi_siswa', $belum->fresh()->status);
        $this->assertSame(1, Siswa::query()->count());
    }
}
