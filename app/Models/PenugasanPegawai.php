<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenugasanPegawai extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tmt' => 'date',
            'tanggal_akhir' => 'date',
            'is_utama' => 'boolean',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }
}
