<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiAsetBaris extends BaseModel
{
    protected $guarded = [];

    public function mutasi(): BelongsTo
    {
        return $this->belongsTo(MutasiAset::class, 'mutasi_id');
    }

    public function asetUnit(): BelongsTo
    {
        return $this->belongsTo(AsetUnit::class, 'aset_unit_id');
    }

    public function ruangAsal(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_asal_id');
    }
}
