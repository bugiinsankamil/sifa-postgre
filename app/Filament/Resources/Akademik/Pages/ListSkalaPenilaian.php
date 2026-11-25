<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\SkalaPenilaianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSkalaPenilaian extends ListRecords
{
    protected static string $resource = SkalaPenilaianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}