<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemetaanAkun extends BaseModel
{
    protected $guarded = [];

    public function entitas(): BelongsTo
    {
        return $this->belongsTo(EntitasAkuntansi::class, 'entitas_id');
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }
}
