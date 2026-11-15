<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaldoAkunPeriode extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'kredit' => 'decimal:2',
            'saldo_akhir' => 'decimal:2',
        ];
    }

    public function entitas(): BelongsTo
    {
        return $this->belongsTo(EntitasAkuntansi::class, 'entitas_id');
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkuntansi::class, 'periode_id');
    }
}
