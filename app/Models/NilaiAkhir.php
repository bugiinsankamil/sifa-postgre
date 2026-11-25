<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiAkhir extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nilai_akhir' => 'decimal:2',
            'kkm' => 'decimal:2',
            'is_tuntas' => 'boolean',
            'status' => 'string',
        ];
    }

    public function penugasanMengajar(): BelongsTo
    {
        return $this->belongsTo(PenugasanMengajar::class, 'penugasan_mengajar_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function skalaButir(): BelongsTo
    {
        return $this->belongsTo(SkalaPenilaianButir::class, 'skala_butir_id');
    }
}