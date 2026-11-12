<?php

namespace App\Models;

class SpmbTahapSeleksi extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['bobot' => 'decimal:2', 'nilai_lulus' => 'decimal:2'];
    }
}
