<?php

namespace App\Models;

class SpmbPersyaratanBerkas extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_wajib' => 'boolean'];
    }
}
