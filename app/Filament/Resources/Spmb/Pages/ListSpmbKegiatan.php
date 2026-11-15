<?php

namespace App\Filament\Resources\Spmb\Pages;

use App\Filament\Resources\Spmb\SpmbKegiatanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpmbKegiatan extends ListRecords
{
    protected static string $resource = SpmbKegiatanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
