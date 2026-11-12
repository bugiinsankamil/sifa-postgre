<?php

namespace App\Models;

class TahunAjaran extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'is_aktif' => 'boolean'];
    }
}
