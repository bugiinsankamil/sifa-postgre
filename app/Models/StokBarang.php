<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokBarang extends BaseModel
{
    protected $guarded = [];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
