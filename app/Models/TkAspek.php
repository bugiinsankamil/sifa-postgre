<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TkAspek extends BaseModel
{

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(TkAspek::class, 'induk_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(TkAspek::class, 'induk_id');
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(TkIndikator::class, 'aspek_id');
    }
}
