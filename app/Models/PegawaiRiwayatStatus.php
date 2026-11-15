<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiRiwayatStatus extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tmt' => 'date'];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
