<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\PembayaranResource;
use App\Services\Keuangan\PembayaranService;
use App\Exceptions\Keuangan\PembayaranException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePembayaran extends CreateRecord
{
    protected static string $resource = PembayaranResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return app(PembayaranService::class)->buat([
                'cabang_id' => $data['cabang_id'],
                'entitas_id' => $data['entitas_id'],
                'siswa_id' => $data['siswa_id'] ?? null,
                'pendaftar_id' => $data['pendaftar_id'] ?? null,
                'tanggal' => $data['tanggal'],
                'metode' => $data['metode'],
                'nominal' => $data['nominal'],
                'rekening_id' => $data['rekening_id'],
                'referensi_eksternal' => $data['referensi_eksternal'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw $e;
        }
    }
}
