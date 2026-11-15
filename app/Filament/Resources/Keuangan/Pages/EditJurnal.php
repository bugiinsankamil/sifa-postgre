<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\JurnalResource;
use App\Services\Keuangan\JurnalService;
use Filament\Resources\Pages\EditRecord;

class EditJurnal extends EditRecord
{
    protected static string $resource = JurnalResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $baris = $data['baris'] ?? [];
        unset($data['baris']);

        return app(JurnalService::class)->ubahDraft($record, $data, $baris);
    }
}
