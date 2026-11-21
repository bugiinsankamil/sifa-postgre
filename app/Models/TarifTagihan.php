<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifTagihan extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'jadwal' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class, 'jenis_tagihan_id');
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(Tingkat::class, 'tingkat_id');
    }
}
