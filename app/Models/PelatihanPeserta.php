<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PelatihanPeserta extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['nilai' => 'decimal:2'];
    }

    public function pelatihan(): BelongsTo
    {
        return $this->belongsTo(Pelatihan::class, 'pelatihan_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
