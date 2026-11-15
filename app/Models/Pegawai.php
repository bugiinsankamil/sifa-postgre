<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public function pendidikan(): HasMany
    {
        return $this->hasMany(PegawaiPendidikan::class, 'pegawai_id');
    }

    public function keluarga(): HasMany
    {
        return $this->hasMany(PegawaiKeluarga::class, 'pegawai_id');
    }

    public function pajakBpjs(): HasOne
    {
        return $this->hasOne(PegawaiPajakBpjs::class, 'pegawai_id');
    }

    public function rekening(): HasMany
    {
        return $this->hasMany(RekeningPegawai::class, 'pegawai_id');
    }

    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(PegawaiRiwayatStatus::class, 'pegawai_id');
    }

    public function kontrak(): HasMany
    {
        return $this->hasMany(KontrakKerja::class, 'pegawai_id');
    }

    public function kontrakAktif(): HasOne
    {
        return $this->hasOne(KontrakKerja::class, 'pegawai_id')->where('status', 'aktif');
    }

    public function sertifikasi(): HasMany
    {
        return $this->hasMany(PegawaiSertifikasi::class, 'pegawai_id');
    }

    public function shift(): HasMany
    {
        return $this->hasMany(PegawaiShift::class, 'pegawai_id');
    }

    public function pelatihan(): HasMany
    {
        return $this->hasMany(PelatihanPeserta::class, 'pegawai_id');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(AbsensiPegawai::class, 'pegawai_id');
    }

    public function absensiLogMentah(): HasMany
    {
        return $this->hasMany(AbsensiLogMentah::class, 'pegawai_id');
    }
}
