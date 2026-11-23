<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BerLingkupCabang;

class Jenjang extends BaseModel
{
    use BerLingkupCabang;

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