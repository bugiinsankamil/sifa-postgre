<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\PerangkatAbsensiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerangkatAbsensi extends ListRecords
{
    protected static string $resource = PerangkatAbsensiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
