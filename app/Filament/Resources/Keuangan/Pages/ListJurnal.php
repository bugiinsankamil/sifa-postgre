<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\JurnalResource;
use App\Services\Keuangan\JurnalService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListJurnal extends ListRecords
{
    protected static string $resource = JurnalResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('posting')
                ->label('Posting')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === 'draft' && \App\Filament\Resources\Keuangan\JurnalResource::canPost($record))
                ->action(function ($record) {
                    try {
                        app(JurnalService::class)->posting($record, auth()->id());
                        Notification::make()->title('Jurnal diposting')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Gagal posting')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
