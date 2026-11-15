<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\JurnalResource;
use App\Services\Keuangan\JurnalService;
use Filament\Resources\Pages\CreateRecord;

class CreateJurnal extends CreateRecord
{
    protected static string $resource = JurnalResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $baris = $data['baris'] ?? [];
        unset($data['baris']);

        $data['dibuat_oleh'] = auth()->id();
        $data['cabang_id'] = $data['cabang_id'] ?? null;

        return app(JurnalService::class)->buatDraft($data, $baris);
    }
}
