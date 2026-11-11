<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;

class SiswaSekolah extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal_masuk' => 'date', 'tanggal_keluar' => 'date'];
    }
}
