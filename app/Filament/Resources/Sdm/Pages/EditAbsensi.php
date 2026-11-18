<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\AbsensiResource;
use App\Models\Pegawai;
use App\Services\Sdm\AbsensiService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditAbsensi extends EditRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // Pegawai dan tanggal dikunci pada record asli; mengubahnya akan membuat absensi lain secara diam-diam
        $pegawai = Pegawai::findOrFail($record->pegawai_id);

        try {
            return app(AbsensiService::class)->catatManual($pegawai, $record->tanggal->toDateString(), [
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
