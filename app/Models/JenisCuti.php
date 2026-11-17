<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisCuti extends BaseModel
{
    protected $guarded = [];

    /** Cerminan default migrasi agar model yang baru dibuat langsung aktif. */
    protected $attributes = [
        'is_aktif' => true,
        'is_dibayar' => true,
        'perlu_lampiran' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_dibayar' => 'boolean',
            'perlu_lampiran' => 'boolean',
            'is_aktif' => 'boolean',
            'aturan' => 'array',
        ];
    }

    public function saldo(): HasMany
    {
        return $this->hasMany(SaldoCuti::class, 'jenis_cuti_id');
    }

    public function pengajuan(): HasMany
    {
        return $this->hasMany(PengajuanCuti::class, 'jenis_cuti_id');
    }
}
