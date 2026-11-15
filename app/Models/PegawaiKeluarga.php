<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiKeluarga extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_tanggungan' => 'boolean',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
