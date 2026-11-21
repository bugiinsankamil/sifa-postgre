<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaDiskon extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_akhir' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function diskon(): BelongsTo
    {
        return $this->belongsTo(DiskonBeasiswa::class, 'diskon_id');
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class, 'jenis_tagihan_id');
    }
}
