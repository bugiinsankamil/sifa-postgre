<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GajiPegawaiKomponen extends BaseModel
{
    protected $guarded = [];

    public function gajiPegawai(): BelongsTo
    {
        return $this->belongsTo(GajiPegawai::class, 'gaji_pegawai_id');
    }

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenGaji::class, 'komponen_id');
    }
}
