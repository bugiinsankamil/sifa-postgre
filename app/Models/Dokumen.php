<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dokumen extends BaseModel
{
    use BerLingkupCabang, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_dokumen' => 'date',
            'tanggal_retensi_habis' => 'date',
            'tag' => 'array',
        ];
    }

    public function klasifikasi(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiArsip::class, 'klasifikasi_id');
    }

    public function lokasiFisik(): BelongsTo
    {
        return $this->belongsTo(LokasiArsipFisik::class, 'lokasi_fisik_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function versi(): HasMany
    {
        return $this->hasMany(DokumenVersi::class, 'dokumen_id');
    }

    public function akses(): HasMany
    {
        return $this->hasMany(DokumenAkses::class, 'dokumen_id');
    }
}
