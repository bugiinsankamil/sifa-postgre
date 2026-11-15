<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisCuti extends BaseModel
{
    protected $guarded = [];

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
