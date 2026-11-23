<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BerLingkupCabang;

class KurikulumSekolah extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class);
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function tahun_ajaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(Tingkat::class);
    }
}