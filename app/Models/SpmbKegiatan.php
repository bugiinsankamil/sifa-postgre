<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpmbKegiatan extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(SpmbPeriode::class, 'periode_id');
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(SpmbKegiatanPeserta::class, 'kegiatan_id');
    }
}
