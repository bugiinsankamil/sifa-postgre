<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlipGaji extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_pendapatan' => 'decimal:2',
            'total_potongan' => 'decimal:2',
            'pph21' => 'decimal:2',
            'gaji_bersih' => 'decimal:2',
            'tanggal_bayar' => 'date',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeGaji::class, 'periode_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(RekeningPegawai::class, 'rekening_pegawai_id');
    }

    public function rincian(): HasMany
    {
        return $this->hasMany(SlipGajiRincian::class, 'slip_id');
    }
}
