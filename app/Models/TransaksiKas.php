<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiKas extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'nominal' => 'decimal:2',
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

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(RekeningKas::class, 'rekening_id');
    }

    public function akunLawan(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_lawan_id');
    }

    public function rekeningTujuan(): BelongsTo
    {
        return $this->belongsTo(RekeningKas::class, 'rekening_tujuan_id');
    }

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class, 'jurnal_id');
    }

    public function rekonsiliasi(): BelongsTo
    {
        return $this->belongsTo(RekonsiliasiBank::class, 'rekonsiliasi_id');
    }
}
