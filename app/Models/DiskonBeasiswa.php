<?php

namespace App\Models;

class DiskonBeasiswa extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'is_aktif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
