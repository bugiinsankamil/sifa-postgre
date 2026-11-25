<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\ModulAjarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModulAjar extends ListRecords
{
    protected static string $resource = ModulAjarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}