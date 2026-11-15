<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokOpname extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function baris(): HasMany
    {
        return $this->hasMany(StokOpnameBaris::class, 'opname_id');
    }
}
