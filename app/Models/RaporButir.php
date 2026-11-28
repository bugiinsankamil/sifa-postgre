<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaporButir extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nilai_angka' => 'decimal:2',
            'data' => 'array',
        ];
    }

    public function rapor(): BelongsTo
    {
        return $this->belongsTo(Rapor::class, 'rapor_id');
    }

    public function bagian(): BelongsTo
    {
        return $this->belongsTo(RaporTemplatBagian::class, 'bagian_id');
    }
}
