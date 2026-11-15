<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkemaGajiKomponen extends BaseModel
{
    protected $guarded = [];

    public function skema(): BelongsTo
    {
        return $this->belongsTo(SkemaGaji::class, 'skema_id');
    }

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenGaji::class, 'komponen_id');
    }
}
