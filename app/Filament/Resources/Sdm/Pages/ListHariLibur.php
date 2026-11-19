<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\HariLiburResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHariLibur extends ListRecords
{
    protected static string $resource = HariLiburResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}