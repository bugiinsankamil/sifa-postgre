<?php

namespace App\Models\Scopes;

use App\Support\CabangKonteks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class LingkupCabang implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $diizinkan = app(CabangKonteks::class)->cabangDiizinkan();

        // null = tanpa batas (yayasan, console, queue). Array kosong = tidak ada akses sama sekali.
        if ($diizinkan === null) {
            return;
        }

        $builder->whereIn($model->qualifyColumn('cabang_id'), $diizinkan);
    }
}
