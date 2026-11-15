<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermintaanBarangBaris extends BaseModel
{
    protected $guarded = [];

    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(PermintaanBarang::class, 'permintaan_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
