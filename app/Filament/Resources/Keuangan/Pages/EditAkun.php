<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\AkunResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAkun extends EditRecord
{
    protected static string $resource = AkunResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
