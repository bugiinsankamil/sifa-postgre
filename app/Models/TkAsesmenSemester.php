<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TkAsesmenSemester extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => 'string'];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function aspek(): BelongsTo
    {
        return $this->belongsTo(TkAspek::class, 'aspek_id');
    }

    public function skalaButir(): BelongsTo
    {
        return $this->belongsTo(SkalaPenilaianButir::class, 'skala_butir_id');
    }

    public function disusunOleh(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'disusun_oleh');
    }
}
