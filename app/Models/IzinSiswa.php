<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Izin/sakit siswa dengan rentang tanggal.
 */
class IzinSiswa extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function diajukanOleh(): BelongsTo
    {
        return $this->belongsTo(Orang::class, 'diajukan_oleh');
    }
}
