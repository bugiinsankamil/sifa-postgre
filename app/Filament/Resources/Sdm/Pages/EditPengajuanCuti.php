<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Exceptions\Sdm\SdmException;
use App\Filament\Resources\Sdm\PengajuanCutiResource;
use App\Filament\Resources\Sdm\PegawaiResource;
use App\Models\PengajuanCuti;
use App\Services\Sdm\CutiService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPengajuanCuti extends EditRecord
{
    protected static string $resource = PengajuanCutiResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $pengganti = filled($data['pengganti_id'] ?? null)
            ? PegawaiResource::getEloquentQuery()->findOrFail($data['pengganti_id'])
            : null;

        try {
            return app(CutiService::class)->ubahPengajuan(
                $record, $data['alasan'] ?? $record->alasan, $pengganti,
            );
        } catch (SdmException $e) {
            Notification::make()->title('Gagal mengubah cuti')->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }
}
