<?php

namespace App\Filament\Widgets;

use App\Support\CabangKonteks;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CorongSpmb extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return app(CabangKonteks::class)->punyaPeran(config('spmb.peran_panitia', []));
    }

    protected function getStats(): array
    {
        $konteks = app(CabangKonteks::class);
        $ids = $konteks->cabangDiizinkan();

        $query = DB::table('v_spmb_corong');
        if ($ids !== null) {
            $query->whereIn('cabang_id', $ids);
        }

        $baris = $query->selectRaw('
            COALESCE(SUM(jumlah_kontak), 0) as kontak,
            COALESCE(SUM(dihubungi), 0) as dihubungi,
            COALESCE(SUM(jumlah_pendaftar), 0) as pendaftar,
            COALESCE(SUM(lulus), 0) as lulus,
            COALESCE(SUM(daftar_ulang), 0) as daftar_ulang
        ')->first();

        return [
            Stat::make('Kontak prospek', $baris->kontak),
            Stat::make('Sudah dihubungi', $baris->dihubungi),
            Stat::make('Mendaftar', $baris->pendaftar),
            Stat::make('Lulus', $baris->lulus),
            Stat::make('Daftar ulang', $baris->daftar_ulang),
        ];
    }
}
