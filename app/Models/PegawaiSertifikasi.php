<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiSertifikasi extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date',
            'tanggal_kedaluwarsa' => 'date',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
