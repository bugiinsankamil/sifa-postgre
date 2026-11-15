<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruang extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'luas_m2' => 'decimal:2',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function bangunan(): BelongsTo
    {
        return $this->belongsTo(Bangunan::class, 'bangunan_id');
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'penanggung_jawab_id');
    }

    public function asetUnit(): HasMany
    {
        return $this->hasMany(AsetUnit::class, 'ruang_id');
    }
}
