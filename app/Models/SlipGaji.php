<?php

namespace App\Models;

use App\Support\CabangKonteks;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlipGaji extends BaseModel
{
    protected $guarded = [];

    /**
     * Pembatasan cabang berdasarkan periode (tanpa trait karena tidak punya cabang_id langsung).
     */
    protected static function booted(): void
    {
        static::addGlobalScope('lingkup_cabang', function ($builder) {
            $ids = app(CabangKonteks::class)->cabangDiizinkan();
            if ($ids !== null) {
                $builder->whereHas('periode', fn ($q) => $q->whereIn('cabang_id', $ids));
            }
        });
    }

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
