<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaporTemplatBagian extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['konfigurasi' => 'array', 'is_tampil' => 'boolean'];
    }

    public function templat(): BelongsTo
    {
        return $this->belongsTo(RaporTemplat::class, 'templat_id');
    }
}
