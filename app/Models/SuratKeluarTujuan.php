<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratKeluarTujuan extends BaseModel
{
    protected $guarded = [];

    public function suratKeluar(): BelongsTo
    {
        return $this->belongsTo(SuratKeluar::class, 'surat_keluar_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function orang(): BelongsTo
    {
        return $this->belongsTo(Orang::class, 'orang_id');
    }
}
