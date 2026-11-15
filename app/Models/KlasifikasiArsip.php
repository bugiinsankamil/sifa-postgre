<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KlasifikasiArsip extends BaseModel
{
    protected $guarded = [];

    public function induk(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiArsip::class, 'induk_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(KlasifikasiArsip::class, 'induk_id');
    }
}
