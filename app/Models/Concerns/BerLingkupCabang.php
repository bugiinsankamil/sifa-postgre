<?php

namespace App\Models\Concerns;

use App\Models\Scopes\LingkupCabang;
use App\Support\CabangKonteks;

trait BerLingkupCabang
{
    protected static function bootBerLingkupCabang(): void
    {
        static::addGlobalScope(new LingkupCabang());

        // Bila pengguna hanya punya akses ke satu cabang, cabang_id terisi otomatis saat data dibuat
        static::creating(function ($model) {
            if (empty($model->cabang_id)) {
                $tunggal = app(CabangKonteks::class)->cabangTunggal();
                if ($tunggal !== null) {
                    $model->cabang_id = $tunggal;
                }
            }
        });
    }
}
