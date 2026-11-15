<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokOpnameBaris extends BaseModel
{
    protected $guarded = [];

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StokOpname::class, 'opname_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
