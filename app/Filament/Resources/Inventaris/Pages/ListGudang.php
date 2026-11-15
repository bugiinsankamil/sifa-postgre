<?php

namespace App\Filament\Resources\Inventaris\Pages;

use App\Filament\Resources\Inventaris\GudangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGudang extends ListRecords
{
    protected static string $resource = GudangResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
