<?php

namespace App\Models;

class SpmbHasil extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal_keputusan' => 'date', 'skor_total' => 'decimal:2'];
    }
}
