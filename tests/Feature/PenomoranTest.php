<?php

namespace Tests\Feature;

use App\Services\PenomoranDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PenomoranTest extends TestCase
{
    use RefreshDatabase;

    private function buatCabang(string $kode): int
    {
        return DB::table('cabang')->insertGetId(['kode' => $kode, 'nama' => 'Cabang '.$kode, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_nomor_berurutan_dengan_pola_bawaan(): void
    {
        $layanan = new PenomoranDokumen();

        $this->assertSame('00001', $layanan->berikutnya('uji'));
        $this->assertSame('00002', $layanan->berikutnya('uji'));
    }

    public function test_pola_dengan_cabang_dan_tahun_dan_penghitung_terpisah_per_cabang(): void
    {
        $a = $this->buatCabang('FTN-01');
        $b = $this->buatCabang('FTN-02');
        $layanan = new PenomoranDokumen();
        $pola = 'SPMB/{CABANG}/{TAHUN}/{SEQ:4}';

        $this->assertSame('SPMB/FTN-01/2026/0001', $layanan->berikutnya('spmb', $a, 2026, $pola));
        $this->assertSame('SPMB/FTN-01/2026/0002', $layanan->berikutnya('spmb', $a, 2026, $pola));
        $this->assertSame('SPMB/FTN-02/2026/0001', $layanan->berikutnya('spmb', $b, 2026, $pola));
        $this->assertSame('SPMB/FTN-01/2027/0001', $layanan->berikutnya('spmb', $a, 2027, $pola));
    }

    public function test_pola_yang_sudah_tersimpan_tidak_ditimpa_pola_bawaan(): void
    {
        $layanan = new PenomoranDokumen();
        $layanan->berikutnya('surat', null, null, 'S-{SEQ:3}');

        $this->assertSame('S-002', $layanan->berikutnya('surat', null, null, 'POLA-LAIN-{SEQ:9}'));
    }
}
