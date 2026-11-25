<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\NilaiAkhirResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNilaiAkhir extends ListRecords
{
    protected static string $resource = NilaiAkhirResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}