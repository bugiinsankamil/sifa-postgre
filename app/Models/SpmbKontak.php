<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use App\Support\Telepon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpmbKontak extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kontak_pertama_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Nomor dinormalkan sebelum disimpan karena unique index memakai nilai telepon apa adanya
        static::saving(function (self $kontak) {
            $kontak->telepon = Telepon::normal($kontak->telepon);
            $kontak->whatsapp = Telepon::normal($kontak->whatsapp);
        });
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function kampanye(): BelongsTo
    {
        return $this->belongsTo(SpmbKampanye::class, 'kampanye_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'petugas_id');
    }

    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(SpmbTindakLanjut::class, 'kontak_id');
    }
}
