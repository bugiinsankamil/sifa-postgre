<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\RekonsiliasiBankResource;
use App\Services\Keuangan\RekonsiliasiBankService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateRekonsiliasiBank extends CreateRecord
{
    protected static string $resource = RekonsiliasiBankResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return app(RekonsiliasiBankService::class)->mulai(
                (int) $data['rekening_id'],
                (int) $data['tahun'],
                (int) $data['bulan'],
                $data['saldo_bank']
            );
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw $e;
        }
    }
}
