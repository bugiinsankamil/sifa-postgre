<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;

class SpmbPeriode extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_buka' => 'date',
            'tanggal_tutup' => 'date',
            'biaya_pendaftaran' => 'decimal:2',
        ];
    }
}
