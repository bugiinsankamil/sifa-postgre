<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\PengajuanIzinResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPengajuanIzin extends EditRecord
{
    protected static string $resource = PengajuanIzinResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return app(\App\Services\Sdm\IzinService::class)->ubahPengajuan(
                $record, $data['alasan'] ?? $record->alasan,
            );
        } catch (\App\Exceptions\Sdm\SdmException $e) {
            Notification::make()->title('Gagal mengubah izin')->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }
}
