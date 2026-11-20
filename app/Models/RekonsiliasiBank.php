<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RekonsiliasiBank extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'saldo_bank' => 'decimal:2',
            'saldo_buku' => 'decimal:2',
            'selisih' => 'decimal:2',
        ];
    }

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(RekeningKas::class, 'rekening_id');
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(TransaksiKas::class, 'rekonsiliasi_id');
    }
}
