<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KontrakKerja extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'gaji_pokok' => 'decimal:2',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
