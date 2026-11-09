<?php

namespace Tests\Feature;

use App\Models\SpmbKontak;
use App\Models\SpmbPendaftar;
use App\Models\SpmbPeriode;
use Database\Seeders\JenjangSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpmbTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JenjangSeeder::class);
    }

    /** @return array{cabang:int, sekolah:int, tahun:int, periode:SpmbPeriode, jalur:int} */
    private function siapkan(string $kodeCabang = 'FTN-01'): array
    {
        $waktu = ['created_at' => now(), 'updated_at' => now()];

        $cabang = DB::table('cabang')->insertGetId(['kode' => $kodeCabang, 'nama' => 'Cabang '.$kodeCabang] + $waktu);
        $sekolah = DB::table('sekolah')->insertGetId([
            'cabang_id' => $cabang,
            'jenjang_id' => DB::table('jenjang')->where('kode', 'SD')->value('id'),
            'nama' => 'SDIT '.$kodeCabang,
        ] + $waktu);
        $tahun = DB::table('tahun_ajaran')->where('kode', '2026/2027')->value('id')
            ?? DB::table('tahun_ajaran')->insertGetId([
                'kode' => '2026/2027', 'nama' => 'Tahun Ajaran 2026/2027',
                'tanggal_mulai' => '2026-07-13', 'tanggal_selesai' => '2027-06-26',
            ] + $waktu);

        $periode = SpmbPeriode::create([
            'cabang_id' => $cabang, 'sekolah_id' => $sekolah, 'tahun_ajaran_id' => $tahun,
            'nama' => 'Gelombang 1', 'gelombang' => 1,
            'tanggal_buka' => '2026-11-01', 'tanggal_tutup' => '2026-12-31', 'kuota' => 30,
        ]);
        $jalur = DB::table('spmb_jalur')->insertGetId(['periode_id' => $periode->id, 'nama' => 'Reguler'] + $waktu);

        return compact('cabang', 'sekolah', 'tahun', 'periode', 'jalur');
    }

    private function pendaftar(array $s, array $atribut = []): SpmbPendaftar
    {
        return SpmbPendaftar::create($atribut + [
            'periode_id' => $s['periode']->id, 'jalur_id' => $s['jalur'],
            'cabang_id' => $s['cabang'], 'sekolah_id' => $s['sekolah'],
            'tanggal_daftar' => '2026-11-10', 'nama_lengkap' => 'Calon Siswa', 'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Palembang', 'tanggal_lahir' => '2020-03-15',
        ]);
    }

    public function test_nomor_pendaftaran_dibuat_otomatis_dan_berurutan(): void
    {
        $s = $this->siapkan();

        $this->assertSame('SPMB/FTN-01/2026/0001', $this->pendaftar($s)->nomor_pendaftaran);
        $this->assertSame('SPMB/FTN-01/2026/0002', $this->pendaftar($s)->nomor_pendaftaran);
    }

    public function test_nik_pendaftar_terenkripsi_dan_tidak_boleh_ganda_dalam_satu_periode(): void
    {
        $s = $this->siapkan();
        $p = $this->pendaftar($s, ['nik' => '1671012345678901']);

        $this->assertNotSame('1671012345678901', DB::table('spmb_pendaftar')->where('id', $p->id)->value('nik'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->pendaftar($s, ['nik' => '1671-0123-4567-8901']);
    }

    public function test_periode_dengan_sekolah_dari_cabang_lain_ditolak(): void
    {
        $a = $this->siapkan('FTN-01');
        $b = $this->siapkan('FTN-02');

        $this->expectException(QueryException::class);
        SpmbPeriode::create([
            'cabang_id' => $a['cabang'], 'sekolah_id' => $b['sekolah'], 'tahun_ajaran_id' => $a['tahun'],
            'nama' => 'Salah', 'gelombang' => 2,
            'tanggal_buka' => '2026-11-01', 'tanggal_tutup' => '2026-12-31', 'kuota' => 10,
        ]);
    }

    public function test_pendaftar_dengan_jalur_dari_periode_lain_ditolak(): void
    {
        $a = $this->siapkan('FTN-01');
        $b = $this->siapkan('FTN-02');

        $this->expectException(QueryException::class);
        $this->pendaftar($a, ['jalur_id' => $b['jalur']]);
    }

    private function kontak(array $s, array $atribut = []): SpmbKontak
    {
        return SpmbKontak::create($atribut + [
            'cabang_id' => $s['cabang'], 'tahun_ajaran_id' => $s['tahun'],
            'nama_kontak' => 'Bapak Budi', 'telepon' => '0812-3456-7890',
            'nama_calon_siswa' => 'Aisyah', 'sumber_ref' => 'instagram',
        ]);
    }

    public function test_nomor_telepon_dinormalkan_dan_kontak_ganda_ditolak(): void
    {
        $s = $this->siapkan();
        $pertama = $this->kontak($s);

        $this->assertSame('6281234567890', $pertama->telepon);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->kontak($s, ['telepon' => '+62 812 3456 7890', 'nama_calon_siswa' => 'AISYAH']);
    }

    public function test_nomor_sama_boleh_untuk_anak_yang_berbeda_dan_cabang_yang_berbeda(): void
    {
        $a = $this->siapkan('FTN-01');
        $b = $this->siapkan('FTN-02');

        $this->kontak($a);
        $this->kontak($a, ['nama_calon_siswa' => 'Umar']);
        $this->kontak($b);

        $this->assertSame(3, SpmbKontak::query()->count());
    }

    public function test_view_corong_menghitung_kontak_dan_pendaftar(): void
    {
        $s = $this->siapkan();
        $kontak = $this->kontak($s, ['status' => 'dihubungi']);
        $this->kontak($s, ['nama_calon_siswa' => 'Umar']);
        $this->pendaftar($s, ['kontak_id' => $kontak->id]);

        $baris = DB::table('v_spmb_corong')->where('cabang_id', $s['cabang'])->first();

        $this->assertSame(2, (int) $baris->jumlah_kontak);
        $this->assertSame(1, (int) $baris->dihubungi);
        $this->assertSame(1, (int) $baris->jumlah_pendaftar);
    }
}
