<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LokasiArsipFisik extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    public function induk(): BelongsTo
    {
        return $this->belongsTo(LokasiArsipFisik::class, 'induk_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(LokasiArsipFisik::class, 'induk_id');
    }
}
