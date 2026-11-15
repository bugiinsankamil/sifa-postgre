<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SkemaGaji extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function komponen(): HasMany
    {
        return $this->hasMany(SkemaGajiKomponen::class, 'skema_id');
    }

    public function gajiPegawai(): HasMany
    {
        return $this->hasMany(GajiPegawai::class, 'skema_id');
    }
}
