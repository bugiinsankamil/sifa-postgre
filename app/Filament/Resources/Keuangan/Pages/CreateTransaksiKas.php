<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\TransaksiKasResource;
use App\Services\Keuangan\KasService;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateTransaksiKas extends CreateRecord
{
    protected static string $resource = TransaksiKasResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $tipe = $data['tipe'] ?? 'masuk';
        unset($data['tipe']);

        /** @var User $penerima */
        $penerima = auth()->user();

        $payload = array_merge($data, [
            'entitas_id' => $data['entitas_id'] ?? null,
            'cabang_id' => $data['cabang_id'] ?? null,
        ]);

        try {
            if ($tipe === 'transfer') {
                $result = app(KasService::class)->transfer($payload, $penerima);
            } elseif ($tipe === 'keluar') {
                $result = app(KasService::class)->keluar($payload, $penerima);
            } else {
                $result = app(KasService::class)->masuk($payload, $penerima);
            }

            return $result['transaksi'];
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw $e;
        }
    }
}
