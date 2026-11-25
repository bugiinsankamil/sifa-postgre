<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siswa extends BaseModel
{
    use SoftDeletes;

    protected $guarded = [];

    public function orang(): BelongsTo
    {
        return $this->belongsTo(Orang::class, 'orang_id');
    }

    protected function casts(): array
    {
        return ['tanggal_masuk_yayasan' => 'date'];
    }
}
