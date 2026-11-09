<?php

namespace Tests\Feature;

use App\Models\Orang;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NikTest extends TestCase
{
    use RefreshDatabase;

    private function buat(string $nama, ?string $nik): Orang
    {
        return Orang::create(['nama_lengkap' => $nama, 'jenis_kelamin' => 'P', 'nik' => $nik]);
    }

    public function test_nik_tersimpan_terenkripsi_dan_terbaca_kembali(): void
    {
        $orang = $this->buat('Aisyah', '1671 0123 4567 8901');

        $mentah = DB::table('orang')->where('id', $orang->id)->value('nik');

        $this->assertNotSame('1671012345678901', $mentah);
        $this->assertSame('1671012345678901', $orang->fresh()->nik);
    }

    public function test_nik_dapat_dicari_lewat_hash(): void
    {
        $orang = $this->buat('Aisyah', '1671012345678901');

        $this->assertSame($orang->id, Orang::cariNik('1671-0123-4567-8901')->value('id'));
        $this->assertNull(Orang::cariNik('1671012345678999')->first());
    }

    public function test_nik_ganda_ditolak(): void
    {
        $this->buat('Aisyah', '1671012345678901');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->buat('Fatimah', '1671012345678901');
    }

    public function test_orang_tanpa_nik_boleh_banyak(): void
    {
        $this->buat('Anak A', null);
        $this->buat('Anak B', null);

        $this->assertSame(2, Orang::query()->whereNull('nik_hash')->count());
    }
}
