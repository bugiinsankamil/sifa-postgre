<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\EntitasAkuntansiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEntitasAkuntansi extends EditRecord
{
    protected static string $resource = EntitasAkuntansiResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
