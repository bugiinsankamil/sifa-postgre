<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenghapusanAset extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'nilai_buku' => 'decimal:2',
            'nilai_jual' => 'decimal:2',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function asetUnit(): BelongsTo
    {
        return $this->belongsTo(AsetUnit::class, 'aset_unit_id');
    }
}
