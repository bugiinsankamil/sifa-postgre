<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkalaPenilaianButir extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nilai_min' => 'decimal:2',
            'nilai_maks' => 'decimal:2',
            'urutan' => 'integer',
        ];
    }

    public function skala(): BelongsTo
    {
        return $this->belongsTo(SkalaPenilaian::class, 'skala_id');
    }
}