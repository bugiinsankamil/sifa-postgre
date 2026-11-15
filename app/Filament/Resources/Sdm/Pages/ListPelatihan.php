<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\PelatihanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPelatihan extends ListRecords
{
    protected static string $resource = PelatihanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
