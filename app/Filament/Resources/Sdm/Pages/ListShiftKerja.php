<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\ShiftKerjaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShiftKerja extends ListRecords
{
    protected static string $resource = ShiftKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
