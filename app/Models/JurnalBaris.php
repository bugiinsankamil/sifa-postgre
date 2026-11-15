<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JurnalBaris extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'kredit' => 'decimal:2',
        ];
    }

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class, 'jurnal_id');
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function pusatBiaya(): BelongsTo
    {
        return $this->belongsTo(PusatBiaya::class, 'pusat_biaya_id');
    }
}
