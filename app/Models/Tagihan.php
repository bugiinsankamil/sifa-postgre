<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tagihan extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'diskon' => 'decimal:2',
            'total' => 'decimal:2',
            'terbayar' => 'decimal:2',
            'tanggal_terbit' => 'date',
            'jatuh_tempo' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function entitas(): BelongsTo
    {
        return $this->belongsTo(EntitasAkuntansi::class, 'entitas_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(SpmbPendaftar::class, 'pendaftar_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class, 'jenis_tagihan_id');
    }

    public function alokasi(): HasMany
    {
        return $this->hasMany(PembayaranAlokasi::class, 'tagihan_id');
    }

    public function sisa(): string
    {
        return bcsub((string) $this->total, (string) $this->terbayar, 2) ?: '0.00';
    }

    public function isLunas(): bool
    {
        return bccomp((string) $this->terbayar, (string) $this->total, 2) >= 0;
    }
}
