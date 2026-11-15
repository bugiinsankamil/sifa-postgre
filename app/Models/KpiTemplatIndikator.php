<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTemplatIndikator extends BaseModel
{
    protected $guarded = [];

    public function templat(): BelongsTo
    {
        return $this->belongsTo(KpiTemplat::class, 'templat_id');
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(KpiIndikator::class, 'indikator_id');
    }
}
