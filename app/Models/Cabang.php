<?php

namespace App\Models;

use App\Support\CabangKonteks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cabang extends BaseModel
{
    use SoftDeletes;

    protected $guarded = [];

    /** Hanya cabang yang boleh diakses pengguna yang sedang login (null = semua). */
    public function scopeDapatDiakses(Builder $query): Builder
    {
        $diizinkan = app(CabangKonteks::class)->cabangDiizinkan();

        return $diizinkan === null ? $query : $query->whereIn($this->qualifyColumn('id'), $diizinkan);
    }
}
