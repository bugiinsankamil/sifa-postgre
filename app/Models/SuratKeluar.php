<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use App\Services\PenomoranDokumen;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuratKeluar extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tanggal_kirim' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $surat) {
            if (blank($surat->nomor_surat)) {
                $tahun = now()->year;
                $surat->nomor_surat = app(PenomoranDokumen::class)
                    ->berikutnya('surat_keluar', (int) $surat->cabang_id, $tahun, 'SK/{CABANG}/{TAHUN}/{SEQ:4}');
            }
        });
    }

    public function klasifikasi(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiArsip::class, 'klasifikasi_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pembuat_id');
    }

    public function penandatangan(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'penandatangan_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(SuratKeluarTujuan::class, 'surat_keluar_id');
    }
}
