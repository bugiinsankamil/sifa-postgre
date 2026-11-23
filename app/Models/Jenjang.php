<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Jenjang extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }

    public function tingkat(): HasMany
    {
        return $this->hasMany(Tingkat::class);
    }

    public function kurikulum(): HasMany
    {
        return $this->hasMany(Kurikulum::class);
    }
}