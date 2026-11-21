<?php

namespace App\Filament\Resources\Keuangan\Pages;

use App\Filament\Resources\Keuangan\TagihanResource;
use App\Services\Keuangan\TagihanService;
use App\Exceptions\Keuangan\TagihanException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTagihan extends CreateRecord
{
    protected static string $resource = TagihanResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return app(TagihanService::class)->buat([
                'cabang_id' => $data['cabang_id'],
                'entitas_id' => $data['entitas_id'],
                'siswa_id' => $data['siswa_id'] ?? null,
                'pendaftar_id' => $data['pendaftar_id'] ?? null,
                'tahun_ajaran_id' => $data['tahun_ajaran_id'],
                'jenis_tagihan_id' => $data['jenis_tagihan_id'],
                'tarif_tagihan_id' => $data['tarif_tagihan_id'],
                'tanggal_terbit' => $data['tanggal_terbit'],
                'jatuh_tempo' => $data['jatuh_tempo'],
                'periode_label' => $data['periode_label'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw $e;
        }
    }
}
