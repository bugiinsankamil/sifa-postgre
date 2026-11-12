<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use App\Models\Concerns\MemilikiNikTerenkripsi;
use App\Services\PenomoranDokumen;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class SpmbPendaftar extends BaseModel
{
    use BerLingkupCabang, MemilikiNikTerenkripsi;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_daftar' => 'date',
            'tanggal_lahir' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Dijalankan setelah hook trait BerLingkupCabang, jadi cabang_id sudah terisi bila perlu
        static::creating(function (self $pendaftar) {
            if (blank($pendaftar->nomor_pendaftaran)) {
                $tahun = Carbon::parse($pendaftar->tanggal_daftar ?? now())->year;

                $pendaftar->nomor_pendaftaran = app(PenomoranDokumen::class)
                    ->berikutnya('spmb_pendaftar', (int) $pendaftar->cabang_id, $tahun, 'SPMB/{CABANG}/{TAHUN}/{SEQ:4}');
            }
        });
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(SpmbPeriode::class, 'periode_id');
    }

    public function jalur(): BelongsTo
    {
        return $this->belongsTo(SpmbJalur::class, 'jalur_id');
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function kontak(): BelongsTo
    {
        return $this->belongsTo(SpmbKontak::class, 'kontak_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function wali(): HasMany
    {
        return $this->hasMany(SpmbPendaftarWali::class, 'pendaftar_id');
    }

    public function berkas(): HasMany
    {
        return $this->hasMany(SpmbBerkas::class, 'pendaftar_id');
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(SpmbNilaiSeleksi::class, 'pendaftar_id');
    }

    public function hasil(): HasOne
    {
        return $this->hasOne(SpmbHasil::class, 'pendaftar_id');
    }

    public function daftarUlang(): HasOne
    {
        return $this->hasOne(SpmbDaftarUlang::class, 'pendaftar_id');
    }
}
