<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModulAjarTujuan extends BaseModel
{
    protected $guarded = [];

    public function modulAjar(): BelongsTo
    {
        return $this->belongsTo(ModulAjar::class, 'modul_ajar_id');
    }

    public function tujuanPembelajaran(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaran::class, 'tujuan_pembelajaran_id');
    }
}