<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplatSurat extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variabel' => 'array',
            'is_aktif' => 'boolean',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }
}
