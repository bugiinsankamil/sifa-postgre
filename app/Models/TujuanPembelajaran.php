<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BerLingkupCabang;

class TujuanPembelajaran extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'fase' => 'string',
            'urutan' => 'integer',
        ];
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class);
    }

    public function mata_pelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(Tingkat::class);
    }
}