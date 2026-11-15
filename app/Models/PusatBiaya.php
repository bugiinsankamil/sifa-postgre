<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PusatBiaya extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }

    public function entitas(): BelongsTo
    {
        return $this->belongsTo(EntitasAkuntansi::class, 'entitas_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }
}
