<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeAkuntansi extends BaseModel
{
    protected $guarded = [];

    public function entitas(): BelongsTo
    {
        return $this->belongsTo(EntitasAkuntansi::class, 'entitas_id');
    }

    public function jurnal(): HasMany
    {
        return $this->hasMany(Jurnal::class, 'periode_id');
    }
}
