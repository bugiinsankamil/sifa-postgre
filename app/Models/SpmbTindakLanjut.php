<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpmbTindakLanjut extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jadwal_berikutnya' => 'date',
        ];
    }

    public function kontak(): BelongsTo
    {
        return $this->belongsTo(SpmbKontak::class, 'kontak_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'petugas_id');
    }
}
