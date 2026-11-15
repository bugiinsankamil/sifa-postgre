<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenGaji extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kena_pajak' => 'boolean',
            'kena_bpjs' => 'boolean',
            'is_aktif' => 'boolean',
            'formula' => 'array',
        ];
    }

    public function skemaGajiKomponen(): HasMany
    {
        return $this->hasMany(SkemaGajiKomponen::class, 'komponen_id');
    }

    public function gajiPegawaiKomponen(): HasMany
    {
        return $this->hasMany(GajiPegawaiKomponen::class, 'komponen_id');
    }

    public function slipRincian(): HasMany
    {
        return $this->hasMany(SlipGajiRincian::class, 'komponen_id');
    }
}
