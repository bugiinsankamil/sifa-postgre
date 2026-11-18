<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanCuti extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'disetujui_atasan_pada' => 'datetime',
            'disetujui_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(JenisCuti::class, 'jenis_cuti_id');
    }

    public function pengganti(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengganti_id');
    }

    public function disetujuiAtasanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_atasan_oleh');
    }

    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function dibatalkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function hari(): HasMany
    {
        return $this->hasMany(PengajuanCutiHari::class, 'pengajuan_cuti_id');
    }

    public function mutasiSaldo(): HasMany
    {
        return $this->hasMany(MutasiSaldoCuti::class, 'pengajuan_cuti_id');
    }
}
