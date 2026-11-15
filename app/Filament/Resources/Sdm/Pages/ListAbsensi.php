<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\AbsensiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAbsensi extends ListRecords
{
    protected static string $resource = AbsensiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
