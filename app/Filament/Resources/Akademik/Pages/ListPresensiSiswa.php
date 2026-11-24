<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\PresensiSiswaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPresensiSiswa extends ListRecords
{
    protected static string $resource = PresensiSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
