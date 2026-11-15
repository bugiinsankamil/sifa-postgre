<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiPenilaian extends BaseModel
{
    protected $guarded = [];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(KpiPeriode::class, 'periode_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'penilai_id');
    }

    public function templat(): BelongsTo
    {
        return $this->belongsTo(KpiTemplat::class, 'templat_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(KpiPenilaianDetail::class, 'penilaian_id');
    }
}
