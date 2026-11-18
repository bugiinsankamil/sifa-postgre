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
        if ($record->status !== 'diajukan') {
            Notification::make()->title('Gagal')->body('Pengajuan izin yang sudah diproses tidak dapat diubah.')->danger()->send();

            throw new Halt();
        }

        // Pegawai, jenis, dan tanggal tidak diubah dari sini; hanya alasan.
        $record->update([
            'alasan' => $data['alasan'] ?? $record->alasan,
        ]);

        return $record;
    }
}
