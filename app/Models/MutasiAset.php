<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MutasiAset extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function cabangAsal(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_asal_id');
    }

    public function cabangTujuan(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_tujuan_id');
    }

    public function ruangTujuan(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_tujuan_id');
    }

    public function baris(): HasMany
    {
        return $this->hasMany(MutasiAsetBaris::class, 'mutasi_id');
    }
}
