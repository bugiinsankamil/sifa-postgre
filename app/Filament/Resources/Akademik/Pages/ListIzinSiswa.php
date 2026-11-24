<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\IzinSiswaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIzinSiswa extends ListRecords
{
    protected static string $resource = IzinSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
