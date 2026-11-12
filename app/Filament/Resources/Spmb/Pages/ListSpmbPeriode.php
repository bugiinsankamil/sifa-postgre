<?php

namespace App\Filament\Resources\Spmb\Pages;

use App\Filament\Resources\Spmb\SpmbPeriodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpmbPeriode extends ListRecords
{
    protected static string $resource = SpmbPeriodeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
