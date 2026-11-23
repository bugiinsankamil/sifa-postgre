<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class MataPelajaran extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'is_aktif' => 'boolean',
        'kelompok_ref' => 'string',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'kelompok_ref' => 'string',
        ];
    }

    public function jenjang(): BelongsTo
    {
        return $this->belongsTo(Jenjang::class, 'jenjang_id');
    }

    public function kurikulum(): BelongsToMany
    {
        return $this->belongsToMany(Kurikulum::class, 'struktur_kurikulum');
    }

    public function struktur(): HasMany
    {
        return $this->hasMany(StrukturKurikulum::class);
    }

    public function kompetensi(): HasMany
    {
        return $this->hasMany(KompetensiGuru::class);
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(TujuanPembelajaran::class);
    }
}