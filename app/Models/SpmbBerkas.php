<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SpmbBerkas extends BaseModel
{
    protected $guarded = [];

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(SpmbPendaftar::class, 'pendaftar_id');
    }

    public function persyaratan(): BelongsTo
    {
        return $this->belongsTo(SpmbPersyaratanBerkas::class, 'persyaratan_id');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiran');
    }
}
