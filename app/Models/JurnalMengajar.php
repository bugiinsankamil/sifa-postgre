<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jurnal kegiatan belajar mengajar per penugasan.
 * jam_pelajaran_id NULL = harian (TK/SD); diisi = per jam (SMP).
 */
class JurnalMengajar extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function penugasanMengajar(): BelongsTo
    {
        return $this->belongsTo(PenugasanMengajar::class, 'penugasan_mengajar_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function jamPelajaran(): BelongsTo
    {
        return $this->belongsTo(JamPelajaran::class, 'jam_pelajaran_id');
    }

    /** Pengajar yang sebenarnya mengajar pada tanggal tersebut (bisa diganti). */
    public function pengajar(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(PresensiSiswa::class, 'jurnal_mengajar_id');
    }
}
