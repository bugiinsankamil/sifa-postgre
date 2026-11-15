<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jabatan extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanPegawai::class, 'jabatan_id');
    }
}
