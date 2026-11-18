<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HariLibur extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'is_cuti_bersama' => 'boolean',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }
}
