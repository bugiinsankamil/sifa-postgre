<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\StrukturKurikulumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStrukturKurikulum extends ListRecords
{
    protected static string $resource = StrukturKurikulumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
