<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\TujuanPembelajaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTujuanPembelajaran extends ListRecords
{
    protected static string $resource = TujuanPembelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
