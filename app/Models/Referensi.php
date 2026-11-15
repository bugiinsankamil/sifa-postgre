<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Referensi extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function scopeKategori(Builder $q, string $kategori): Builder
    {
        return $q->where('kategori', $kategori)->where('is_aktif', true)->orderBy('urutan');
    }

    public static function opsi(string $kategori): array
    {
        return static::kategori($kategori)->pluck('label', 'kode')->all();
    }
}
