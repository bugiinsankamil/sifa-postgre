<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiSiswa extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'nilai_remedial' => 'decimal:2',
        ];
    }

    public function penilaian(): BelongsTo
    {
        return $this->belongsTo(Penilaian::class, 'penilaian_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}