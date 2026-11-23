<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\KompetensiGuruResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKompetensiGuru extends ListRecords
{
    protected static string $resource = KompetensiGuruResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
