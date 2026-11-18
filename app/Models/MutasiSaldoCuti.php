<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat mutasi saldo cuti (pemberian/pemakaian/pengembalian/penyesuaian/carry_over).
 * Append-only: dibentuk oleh service, tidak diedit lewat form CRUD.
 */
class MutasiSaldoCuti extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'jumlah_hari' => 'decimal:1',
            'saldo_sebelum' => 'decimal:1',
            'saldo_sesudah' => 'decimal:1',
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

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanCuti::class, 'pengajuan_cuti_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
