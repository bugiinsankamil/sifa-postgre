<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penilaian extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nilai_maks' => 'decimal:2',
            'status' => 'string',
            'sumber' => 'string',
        ];
    }

    public function penugasanMengajar(): BelongsTo
    {
        return $this->belongsTo(PenugasanMengajar::class, 'penugasan_mengajar_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class, 'komponen_id');
    }

    public function tujuanPembelajaran(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaran::class, 'tujuan_pembelajaran_id');
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class, 'penilaian_id');
    }
}