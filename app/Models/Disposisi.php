<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disposisi extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'batas_waktu' => 'date',
            'dibaca_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    public function suratMasuk(): BelongsTo
    {
        return $this->belongsTo(SuratMasuk::class, 'surat_masuk_id');
    }

    public function dariUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dari_user_id');
    }

    public function kepadaUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepada_user_id');
    }
}
