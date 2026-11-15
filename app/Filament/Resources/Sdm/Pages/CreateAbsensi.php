<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\AbsensiResource;
use App\Models\Pegawai;
use App\Services\Sdm\AbsensiService;
use Filament\Resources\Pages\CreateRecord;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $pegawai = Pegawai::findOrFail($data['pegawai_id']);

        return app(AbsensiService::class)->catatManual($pegawai, $data['tanggal'], [
            'status' => $data['status'],
            'jam_masuk' => $data['jam_masuk'] ?? null,
            'jam_pulang' => $data['jam_pulang'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
        ], auth()->id());
    }
}
