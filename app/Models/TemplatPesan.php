<?php

namespace App\Models;

class TemplatPesan extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['variabel' => 'array', 'is_aktif' => 'boolean'];
    }
}
