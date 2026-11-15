<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenVersi extends BaseModel
{
    protected $guarded = [];

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
