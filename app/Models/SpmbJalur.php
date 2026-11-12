<?php

namespace App\Models;

class SpmbJalur extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['persyaratan' => 'array', 'is_aktif' => 'boolean'];
    }
}
