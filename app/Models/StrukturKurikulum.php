<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\BerLingkupCabang;

class StrukturKurikulum extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_wajib' => 'boolean',
            'kkm' => 'decimal:2',
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