<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModulAjarVersi extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'isi' => 'array',
            'versi' => 'integer',
        ];
    }

    public function modulAjar(): BelongsTo
    {
        return $this->belongsTo(ModulAjar::class, 'modul_ajar_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}