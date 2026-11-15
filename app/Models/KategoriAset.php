<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriAset extends BaseModel
{
    protected $guarded = [];

    public function induk(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'induk_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(KategoriAset::class, 'induk_id');
    }

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'kategori_id');
    }
}
