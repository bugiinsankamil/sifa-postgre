<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiShift extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_akhir' => 'date',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ShiftKerja::class, 'shift_id');
    }
}
