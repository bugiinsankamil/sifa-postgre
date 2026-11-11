<?php

namespace App\Models;

class SiswaWali extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_kontak_utama' => 'boolean',
            'is_penanggung_biaya' => 'boolean',
            'is_serumah' => 'boolean',
        ];
    }
}
