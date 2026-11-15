<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntitasAkuntansi extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(EntitasAkuntansi::class, 'induk_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(EntitasAkuntansi::class, 'induk_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function akun(): HasMany
    {
        return $this->hasMany(Akun::class, 'entitas_id');
    }

    public function periode(): HasMany
    {
        return $this->hasMany(PeriodeAkuntansi::class, 'entitas_id');
    }

    public function jurnal(): HasMany
    {
        return $this->hasMany(Jurnal::class, 'entitas_id');
    }
}
