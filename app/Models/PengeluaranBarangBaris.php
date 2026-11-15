<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengeluaranBarangBaris extends BaseModel
{
    protected $guarded = [];

    public function pengeluaran(): BelongsTo
    {
        return $this->belongsTo(PengeluaranBarang::class, 'pengeluaran_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
