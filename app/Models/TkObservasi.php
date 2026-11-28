<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TkObservasi extends BaseModel
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

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(TkTema::class, 'tema_id');
    }

    public function aspek(): BelongsTo
    {
        return $this->belongsTo(TkAspek::class, 'aspek_id');
    }

    public function pengamat(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengamat_id');
    }
}
