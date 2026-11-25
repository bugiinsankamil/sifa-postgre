<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\AturanOlahNilaiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAturanOlahNilai extends ListRecords
{
    protected static string $resource = AturanOlahNilaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}