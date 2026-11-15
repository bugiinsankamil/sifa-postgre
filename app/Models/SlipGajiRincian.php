<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlipGajiRincian extends BaseModel
{
    protected $guarded = [];

    public function slip(): BelongsTo
    {
        return $this->belongsTo(SlipGaji::class, 'slip_id');
    }

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenGaji::class, 'komponen_id');
    }
}
