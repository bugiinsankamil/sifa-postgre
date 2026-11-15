<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jurnal extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'diposting_pada' => 'datetime',
        ];
    }

    public function entitas(): BelongsTo
    {
        return $this->belongsTo(EntitasAkuntansi::class, 'entitas_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkuntansi::class, 'periode_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function dipostingOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diposting_oleh');
    }

    public function baris(): HasMany
    {
        return $this->hasMany(JurnalBaris::class, 'jurnal_id');
    }

    public function totalDebit(): string
    {
        return $this->baris()->sum('debit');
    }

    public function totalKredit(): string
    {
        return $this->baris()->sum('kredit');
    }
}
