<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\PengajuanCutiResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPengajuanCuti extends EditRecord
{
    protected static string $resource = PengajuanCutiResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        if ($record->status !== 'diajukan') {
            Notification::make()->title('Gagal')->body('Pengajuan cuti yang sudah diproses tidak dapat diubah.')->danger()->send();

            throw new Halt();
        }

        // Pegawai, jenis, tanggal, dan jumlah hari tidak diubah dari sini (memengaruhi saldo); hanya alasan/pengganti.
        $record->update([
            'alasan' => $data['alasan'] ?? $record->alasan,
            'pengganti_id' => $data['pengganti_id'] ?? $record->pengganti_id,
        ]);

        return $record;
    }
}
