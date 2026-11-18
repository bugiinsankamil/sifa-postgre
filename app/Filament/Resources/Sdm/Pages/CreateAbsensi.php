<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\AbsensiResource;
use App\Models\Pegawai;
use App\Filament\Resources\Sdm\PegawaiResource;
use App\Services\Sdm\AbsensiService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Pegawai dicari lewat query resource agar dibatasi cabang yang berhak diakses pengguna
        $pegawai = PegawaiResource::getEloquentQuery()->findOrFail($data['pegawai_id']);

        try {
            return app(AbsensiService::class)->catatManual($pegawai, $data['tanggal'], [
                'status' => $data['status'],
                'jam_masuk' => $data['jam_masuk'] ?? null,
                'jam_pulang' => $data['jam_pulang'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
            ], auth()->id());
        } catch (\RuntimeException $e) {
            Notification::make()->title('Gagal menyimpan absensi')->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }
}
