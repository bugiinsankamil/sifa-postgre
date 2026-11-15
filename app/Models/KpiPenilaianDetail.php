<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiPenilaianDetail extends BaseModel
{
    protected $guarded = [];

    public function penilaian(): BelongsTo
    {
        return $this->belongsTo(KpiPenilaian::class, 'penilaian_id');
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(KpiIndikator::class, 'indikator_id');
    }
}
