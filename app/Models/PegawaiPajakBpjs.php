<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiPajakBpjs extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['npwp' => 'encrypted'];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
