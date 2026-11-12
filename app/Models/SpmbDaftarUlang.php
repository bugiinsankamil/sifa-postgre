<?php

namespace App\Models;

class SpmbDaftarUlang extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }
}
