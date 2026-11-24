<?php

namespace App\Filament\Resources\Akademik\Pages;

use App\Filament\Resources\Akademik\PenugasanMengajarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPenugasanMengajar extends ListRecords
{
    protected static string $resource = PenugasanMengajarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}