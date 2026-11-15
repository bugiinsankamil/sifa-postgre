<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeminjamanBaris extends BaseModel
{
    protected $guarded = [];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(Peminjaman::class, 'peminjaman_id');
    }

    public function asetUnit(): BelongsTo
    {
        return $this->belongsTo(AsetUnit::class, 'aset_unit_id');
    }
}
