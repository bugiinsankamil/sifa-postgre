<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsensiLogMentah extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'diproses_pada' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function perangkat(): BelongsTo
    {
        return $this->belongsTo(PerangkatAbsensi::class, 'perangkat_id');
    }
}
