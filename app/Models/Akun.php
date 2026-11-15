<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Akun extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_header' => 'boolean',
            'is_antar_entitas' => 'boolean',
            'is_aktif' => 'boolean',
        ];
    }

    public function entitas(): BelongsTo
    {
        return $this->belongsTo(EntitasAkuntansi::class, 'entitas_id');
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'induk_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(Akun::class, 'induk_id');
    }
}
