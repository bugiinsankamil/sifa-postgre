<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AntreanPesan extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'respons' => 'array',
            'dijadwalkan_pada' => 'datetime',
            'diproses_pada' => 'datetime',
            'dikirim_pada' => 'datetime',
        ];
    }

    public function templat(): BelongsTo
    {
        return $this->belongsTo(TemplatPesan::class, 'templat_id');
    }
}
