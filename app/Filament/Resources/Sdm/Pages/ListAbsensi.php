<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\AbsensiResource;
use App\Services\Sdm\RekapAbsensiService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAbsensi extends ListRecords
{
    protected static string $resource = AbsensiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ekspor_rekap')
                ->label('Ekspor rekap bulanan (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->authorize(fn () => AbsensiResource::canViewAny())
                ->schema([
                    Select::make('bulan')->options(array_combine(range(1, 12), [
                        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
                    ]))->default((int) now()->format('n'))->required(),
                    Select::make('tahun')->options(
                        collect(range((int) now()->format('Y') - 3, (int) now()->format('Y') + 1))->mapWithKeys(fn ($t) => [$t => $t])->all()
                    )->default((int) now()->format('Y'))->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $csv = app(RekapAbsensiService::class)->csv((int) $data['tahun'], (int) $data['bulan']);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();

                        return null;
                    }

                    $nama = sprintf('rekap-absensi-%04d-%02d.csv', $data['tahun'], $data['bulan']);

                    return response()->streamDownload(function () use ($csv) {
                        echo $csv;
                    }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
            CreateAction::make(),
        ];
    }
}
