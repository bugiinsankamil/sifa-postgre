<?php

namespace App\Filament\Resources\Inventaris\Pages;

use App\Filament\Resources\Inventaris\RuangResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRuang extends EditRecord
{
    protected static string $resource = RuangResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
