<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use App\Models\Concerns\MemilikiNikTerenkripsi;
use App\Services\PenomoranDokumen;
use Illuminate\Support\Carbon;

class SpmbPendaftar extends BaseModel
{
    use BerLingkupCabang, MemilikiNikTerenkripsi;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_daftar' => 'date',
            'tanggal_lahir' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Dijalankan setelah hook trait BerLingkupCabang, jadi cabang_id sudah terisi bila perlu
        static::creating(function (self $pendaftar) {
            if (blank($pendaftar->nomor_pendaftaran)) {
                $tahun = Carbon::parse($pendaftar->tanggal_daftar ?? now())->year;

                $pendaftar->nomor_pendaftaran = app(PenomoranDokumen::class)
                    ->berikutnya('spmb_pendaftar', (int) $pendaftar->cabang_id, $tahun, 'SPMB/{CABANG}/{TAHUN}/{SEQ:4}');
            }
        });
    }
}
