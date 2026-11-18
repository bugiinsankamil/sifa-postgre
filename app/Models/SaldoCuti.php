<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaldoCuti extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'kuota' => 'decimal:1',
            'terpakai' => 'decimal:1',
            'dibawa' => 'decimal:1',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(JenisCuti::class, 'jenis_cuti_id');
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiSaldoCuti::class, 'jenis_cuti_id', 'jenis_cuti_id')
            ->whereColumn('mutasi_saldo_cuti.pegawai_id', 'saldo_cuti.pegawai_id')
            ->whereColumn('mutasi_saldo_cuti.tahun', 'saldo_cuti.tahun');
    }

    /** Sisa saldo sebagai string desimal 1 digit (BCMath, tanpa float). */
    public function sisa(): string
    {
        return bcsub(bcadd((string) $this->kuota, (string) $this->dibawa, 1), (string) $this->terpakai, 1);
    }
}
