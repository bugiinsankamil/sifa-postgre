<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpmbPeriode extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_buka' => 'date',
            'tanggal_tutup' => 'date',
            'biaya_pendaftaran' => 'decimal:2',
            'hasil_diumumkan_pada' => 'datetime',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function jalur(): HasMany
    {
        return $this->hasMany(SpmbJalur::class, 'periode_id');
    }

    public function persyaratan(): HasMany
    {
        return $this->hasMany(SpmbPersyaratanBerkas::class, 'periode_id');
    }

    public function tahap(): HasMany
    {
        return $this->hasMany(SpmbTahapSeleksi::class, 'periode_id');
    }

    public function pendaftar(): HasMany
    {
        return $this->hasMany(SpmbPendaftar::class, 'periode_id');
    }
}
