<?php

namespace App\Filament\Resources\Arsip\Pages;

use App\Filament\Resources\Arsip\SuratMasukResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuratMasuk extends ListRecords
{
    protected static string $resource = SuratMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
