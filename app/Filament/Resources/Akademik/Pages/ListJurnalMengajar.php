<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\JurnalMengajarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJurnalMengajar extends ListRecords
{
    protected static string $resource = JurnalMengajarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
