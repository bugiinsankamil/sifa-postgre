<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengeluaranBarang extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(PermintaanBarang::class, 'permintaan_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'penerima_id');
    }

    public function baris(): HasMany
    {
        return $this->hasMany(PengeluaranBarangBaris::class, 'pengeluaran_id');
    }
}
