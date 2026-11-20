<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RekeningKas extends BaseModel
{
    use BerLingkupCabang;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'saldo_awal' => 'decimal:2',
            'is_aktif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function entitas(): BelongsTo
    {
        return $this->belongsTo(EntitasAkuntansi::class, 'entitas_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(TransaksiKas::class, 'rekening_id');
    }

    public function saldoAkhir(): string
    {
        $masuk = $this->transaksi()->where('tipe', 'masuk')->where('status', 'posted')->sum('nominal');
        $keluar = $this->transaksi()->where('tipe', 'keluar')->where('status', 'posted')->sum('nominal');
        $masukTransfer = $this->transaksi()->where('tipe', 'transfer')->where('rekening_tujuan_id', $this->id)->where('status', 'posted')->sum('nominal');
        $keluarTransfer = $this->transaksi()->where('tipe', 'transfer')->where('rekening_id', $this->id)->where('status', 'posted')->sum('nominal');

        return bcsub(bcadd((string) $this->saldo_awal, (string) $masuk, 2), bcadd((string) $keluar, (string) $keluarTransfer, 2), 2)
            ?: '0.00';
    }
}
