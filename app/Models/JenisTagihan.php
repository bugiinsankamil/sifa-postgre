<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisTagihan extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'boleh_cicil' => 'boolean',
            'is_aktif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function akunPendapatan(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_pendapatan_id');
    }

    public function jenjang(): BelongsTo
    {
        return $this->belongsTo(Jenjang::class, 'jenjang_id');
    }
}
