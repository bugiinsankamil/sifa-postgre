<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AsetUnit extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_perolehan' => 'date',
            'harga_perolehan' => 'decimal:2',
            'nilai_residu' => 'decimal:2',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_id');
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'penanggung_jawab_id');
    }

    public function kendaraan(): HasOne
    {
        return $this->hasOne(AsetKendaraan::class, 'aset_unit_id');
    }
}
