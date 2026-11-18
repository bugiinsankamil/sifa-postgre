<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\PengajuanIzinResource;
use App\Filament\Resources\Sdm\PegawaiResource;
use App\Services\Sdm\IzinService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreatePengajuanIzin extends CreateRecord
{
    protected static string $resource = PengajuanIzinResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $pegawai = PegawaiResource::getEloquentQuery()->findOrFail($data['pegawai_id']);

        try {
            return app(IzinService::class)->ajukan(
                $pegawai,
                $data['jenis'],
                $data['tanggal_mulai'],
                $data['tanggal_selesai'],
                $data['alasan'] ?? null,
            );
        } catch (\RuntimeException $e) {
            Notification::make()->title('Gagal mengajukan izin')->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }
}
