<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenAkses extends BaseModel
{
    protected $guarded = [];

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id');
    }
}
