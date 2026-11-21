<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualAccount extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kedaluwarsa_pada' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
