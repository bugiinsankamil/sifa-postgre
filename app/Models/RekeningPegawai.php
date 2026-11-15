<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekeningPegawai extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nomor_rekening' => 'encrypted',
            'is_utama' => 'boolean',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
