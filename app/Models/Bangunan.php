<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bangunan extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'luas_m2' => 'decimal:2',
            'nilai_perolehan' => 'decimal:2',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function tanah(): BelongsTo
    {
        return $this->belongsTo(Tanah::class, 'tanah_id');
    }

    public function ruang(): HasMany
    {
        return $this->hasMany(Ruang::class, 'bangunan_id');
    }
}
