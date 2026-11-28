<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rapor extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'tanggal_terbit' => 'date',
            'data_tambahan' => 'array',
        ];
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

    public function templat(): BelongsTo
    {
        return $this->belongsTo(RaporTemplat::class, 'templat_id');
    }

    public function ditandatanganiOleh(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'ditandatangani_oleh');
    }

    public function butir(): HasMany
    {
        return $this->hasMany(RaporButir::class, 'rapor_id')->orderBy('urutan');
    }
}
