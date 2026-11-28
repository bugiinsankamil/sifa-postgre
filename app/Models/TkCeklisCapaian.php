<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TkCeklisCapaian extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(TkIndikator::class, 'indikator_id');
    }

    public function skalaButir(): BelongsTo
    {
        return $this->belongsTo(SkalaPenilaianButir::class, 'skala_butir_id');
    }

    public function pengamat(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengamat_id');
    }

    public function observasi(): BelongsTo
    {
        return $this->belongsTo(TkObservasi::class, 'observasi_id');
    }
}
