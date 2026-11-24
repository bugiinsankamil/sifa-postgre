<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\JamPelajaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJamPelajaran extends ListRecords
{
    protected static string $resource = JamPelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}