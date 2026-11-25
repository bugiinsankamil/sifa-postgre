<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AturanOlahNilai extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'formula' => 'array',
            'kkm' => 'decimal:2',
            'skala_id' => 'integer',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(Tingkat::class, 'tingkat_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function skala(): BelongsTo
    {
        return $this->belongsTo(SkalaPenilaian::class, 'skala_id');
    }
}