<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sekolah extends BaseModel
{
    use BerLingkupCabang, SoftDeletes;

    protected $guarded = [];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }
}
