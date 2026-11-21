<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pembayaran extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'tanggal' => 'date',
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

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(RekeningKas::class, 'rekening_id');
    }

    public function diterimaOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    public function transaksiKas(): BelongsTo
    {
        return $this->belongsTo(TransaksiKas::class, 'transaksi_kas_id');
    }

    public function alokasi(): HasMany
    {
        return $this->hasMany(PembayaranAlokasi::class, 'pembayaran_id');
    }

    public function pengembalian(): HasMany
    {
        return $this->hasMany(PengembalianDana::class, 'pembayaran_id');
    }
}
