<?php

namespace App\Filament\Resources\Spmb\Pages;

use App\Filament\Resources\Spmb\SpmbPendaftarResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSpmbPendaftar extends EditRecord
{
    protected static string $resource = SpmbPendaftarResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }
}
