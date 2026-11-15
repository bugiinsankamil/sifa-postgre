<?php

namespace App\Filament\Resources\Inventaris\Pages;

use App\Filament\Resources\Inventaris\RuangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRuang extends ListRecords
{
    protected static string $resource = RuangResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
