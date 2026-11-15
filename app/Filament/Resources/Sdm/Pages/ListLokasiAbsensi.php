<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\LokasiAbsensiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLokasiAbsensi extends ListRecords
{
    protected static string $resource = LokasiAbsensiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
