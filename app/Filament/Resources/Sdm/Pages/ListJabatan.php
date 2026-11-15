<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\JabatanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJabatan extends ListRecords
{
    protected static string $resource = JabatanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
