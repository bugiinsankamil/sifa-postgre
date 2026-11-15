<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTemplat extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id');
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(KpiTemplatIndikator::class, 'templat_id');
    }

    public function penilaian(): HasMany
    {
        return $this->hasMany(KpiPenilaian::class, 'templat_id');
    }
}
