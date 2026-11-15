<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\EntitasAkuntansiResource;
use App\Services\Keuangan\SinkronisasiEntitas;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEntitasAkuntansi extends ListRecords
{
    protected static string $resource = EntitasAkuntansiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sinkronisasi')
                ->label('Sinkronisasi dari Organisasi')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $hasil = app(SinkronisasiEntitas::class)->jalankan();
                        Notification::make()
                            ->title('Sinkronisasi berhasil')
                            ->body("Yayasan: 1, Cabang: " . count($hasil['cabang']) . ", Sekolah: " . count($hasil['sekolah']))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal sinkronisasi')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
