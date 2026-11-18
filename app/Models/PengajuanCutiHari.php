<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanCutiHari extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tahun' => 'integer',
            'nilai' => 'decimal:2',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanCuti::class, 'pengajuan_cuti_id');
    }

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(AbsensiPegawai::class, 'absensi_pegawai_id');
    }
}
