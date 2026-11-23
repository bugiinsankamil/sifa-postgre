<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Tingkat extends BaseModel
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

    public function jenjang(): BelongsTo
    {
        return $this->belongsTo(Jenjang::class);
    }

    public function kurikulum(): HasMany
    {
        return $this->hasMany(Kurikulum::class);
    }

    public function struktur(): HasMany
    {
        return $this->hasMany(StrukturKurikulum::class);
    }

    public function kurikulum_sekolah(): HasMany
    {
        return $this->hasMany(KurikulumSekolah::class);
    }
}