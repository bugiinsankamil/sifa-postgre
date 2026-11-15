<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'harga_standar' => 'decimal:2',
            'is_aktif' => 'boolean',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'kategori_id');
    }

    public function asetUnit(): HasMany
    {
        return $this->hasMany(AsetUnit::class, 'barang_id');
    }

    public function stokBarang(): HasMany
    {
        return $this->hasMany(StokBarang::class, 'barang_id');
    }
}
