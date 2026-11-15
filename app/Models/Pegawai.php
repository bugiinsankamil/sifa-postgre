<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pegawai extends BaseModel
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_keluar' => 'date',
        ];
    }

    public function orang(): BelongsTo
    {
        return $this->belongsTo(Orang::class, 'orang_id');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanPegawai::class, 'pegawai_id');
    }

    /** Penugasan utama yang masih aktif. */
    public function penugasanUtama(): HasMany
    {
        return $this->penugasan()->where('is_utama', true)->whereNull('tanggal_akhir');
    }
}
