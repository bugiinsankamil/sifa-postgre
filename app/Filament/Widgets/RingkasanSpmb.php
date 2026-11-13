<?php

namespace App\Filament\Widgets;

use App\Models\SpmbBerkas;
use App\Models\SpmbDaftarUlang;
use App\Models\SpmbPendaftar;
use App\Support\CabangKonteks;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanSpmb extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return app(CabangKonteks::class)->punyaPeran(config('spmb.peran_panitia', []));
    }

    protected function getStats(): array
    {
        // Model sudah dibatasi ke cabang pengguna oleh global scope
        $hitung = fn (array $status) => SpmbPendaftar::query()->whereIn('status', $status)->count();

        $daftarUlangSelesai = SpmbDaftarUlang::query()
            ->where('status', 'selesai')
            ->whereIn('pendaftar_id', SpmbPendaftar::query()->select('id'))
            ->count();

        $berkasMenunggu = SpmbBerkas::query()
            ->where('status_verifikasi', 'menunggu')
            ->whereIn('pendaftar_id', SpmbPendaftar::query()->select('id'))
            ->count();

        return [
            Stat::make('Total pendaftar', SpmbPendaftar::query()->count()),
            Stat::make('Menunggu verifikasi', $hitung(['terdaftar']))->description($berkasMenunggu.' berkas perlu dicek'),
            Stat::make('Dalam seleksi', $hitung(['diverifikasi', 'seleksi'])),
            Stat::make('Lulus', $hitung(['lulus', 'daftar_ulang', 'menjadi_siswa'])),
            Stat::make('Daftar ulang selesai', $daftarUlangSelesai),
        ];
    }
}
