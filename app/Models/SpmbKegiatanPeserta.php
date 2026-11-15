<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpmbKegiatanPeserta extends BaseModel
{
    protected $guarded = [];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(SpmbKegiatan::class, 'kegiatan_id');
    }

    public function kontak(): BelongsTo
    {
        return $this->belongsTo(SpmbKontak::class, 'kontak_id');
    }
}
