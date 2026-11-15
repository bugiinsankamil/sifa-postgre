<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiIndikator extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function templatIndikator(): HasMany
    {
        return $this->hasMany(KpiTemplatIndikator::class, 'indikator_id');
    }

    public function penilaianDetail(): HasMany
    {
        return $this->hasMany(KpiPenilaianDetail::class, 'indikator_id');
    }
}
