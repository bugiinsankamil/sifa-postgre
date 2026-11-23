<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Kurikulum extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'aturan' => 'array',
        ];
    }

    public function sekolah(): BelongsToMany
    {
        return $this->belongsToMany(Sekolah::class, 'kurikulum_sekolah');
    }

    public function tingkat(): HasMany
    {
        return $this->hasMany(Tingkat::class);
    }

    public function struktur(): HasMany
    {
        return $this->hasMany(StrukturKurikulum::class);
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(TujuanPembelajaran::class);
    }
}