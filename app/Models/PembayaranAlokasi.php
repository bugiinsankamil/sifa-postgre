<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranAlokasi extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'pembayaran_id');
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class, 'tagihan_id');
    }
}
