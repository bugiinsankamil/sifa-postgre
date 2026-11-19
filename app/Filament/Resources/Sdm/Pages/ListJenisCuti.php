<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\JenisCutiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJenisCuti extends ListRecords
{
    protected static string $resource = JenisCutiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}