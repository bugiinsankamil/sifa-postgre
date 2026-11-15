<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\PengajuanIzinResource;
use App\Models\Pegawai;
use App\Services\Sdm\IzinService;
use Filament\Resources\Pages\CreateRecord;

class CreatePengajuanIzin extends CreateRecord
{
    protected static string $resource = PengajuanIzinResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $pegawai = Pegawai::findOrFail($data['pegawai_id']);

        return app(IzinService::class)->ajukan(
            $pegawai,
            $data['jenis'],
            $data['tanggal_mulai'],
            $data['tanggal_selesai'],
            $data['alasan'] ?? null,
        );
    }
}
