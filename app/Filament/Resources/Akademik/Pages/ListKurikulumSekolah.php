<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\KurikulumSekolahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKurikulumSekolah extends ListRecords
{
    protected static string $resource = KurikulumSekolahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
