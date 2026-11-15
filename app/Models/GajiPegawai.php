<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GajiPegawai extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_berlaku' => 'date',
            'gaji_pokok' => 'decimal:2',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function skema(): BelongsTo
    {
        return $this->belongsTo(SkemaGaji::class, 'skema_id');
    }

    public function komponen(): HasMany
    {
        return $this->hasMany(GajiPegawaiKomponen::class, 'gaji_pegawai_id');
    }
}
