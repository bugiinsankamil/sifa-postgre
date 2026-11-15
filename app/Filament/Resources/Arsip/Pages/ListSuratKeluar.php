<?php

namespace App\Filament\Resources\Arsip\Pages;

use App\Filament\Resources\Arsip\SuratKeluarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuratKeluar extends ListRecords
{
    protected static string $resource = SuratKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
