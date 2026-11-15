<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiPendidikan extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['ipk' => 'decimal:2'];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
