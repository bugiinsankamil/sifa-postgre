<?php

namespace App\Models;

class SpmbPendaftarWali extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'is_kontak_utama' => 'boolean',
        ];
    }
}
