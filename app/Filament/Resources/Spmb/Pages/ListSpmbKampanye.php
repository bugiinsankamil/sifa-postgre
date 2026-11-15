<?php

namespace App\Filament\Resources\Spmb\Pages;

use App\Filament\Resources\Spmb\SpmbKampanyeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpmbKampanye extends ListRecords
{
    protected static string $resource = SpmbKampanyeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
