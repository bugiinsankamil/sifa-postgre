<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservasiRuang extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mulai_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_id');
    }

    public function peminjam(): BelongsTo
    {
        return $this->belongsTo(Orang::class, 'peminjam_id');
    }
}
