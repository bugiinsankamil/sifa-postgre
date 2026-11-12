<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpmbNilaiSeleksi extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'nilai' => 'decimal:2'];
    }

    public function tahap(): BelongsTo
    {
        return $this->belongsTo(SpmbTahapSeleksi::class, 'tahap_id');
    }
}
