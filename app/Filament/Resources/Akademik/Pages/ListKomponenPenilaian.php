<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\KomponenPenilaianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKomponenPenilaian extends ListRecords
{
    protected static string $resource = KomponenPenilaianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}