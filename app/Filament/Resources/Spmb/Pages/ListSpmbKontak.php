<?php

namespace App\Filament\Resources\Spmb\Pages;

use App\Filament\Resources\Spmb\SpmbKontakResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpmbKontak extends ListRecords
{
    protected static string $resource = SpmbKontakResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
