<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TkIndikator extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function aspek(): BelongsTo
    {
        return $this->belongsTo(TkAspek::class, 'aspek_id');
    }

    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(Tingkat::class, 'tingkat_id');
    }
}
