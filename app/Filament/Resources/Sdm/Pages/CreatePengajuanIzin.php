<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Exceptions\Sdm\SdmException;
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

        $sesiPerTanggal = [];
        foreach (($data['hari_sesi'] ?? []) as $baris) {
            if (filled($baris['tanggal'] ?? null) && filled($baris['sesi'] ?? null)) {
                $sesiPerTanggal[$baris['tanggal']] = $baris['sesi'];
            }
        }

        try {
            return app(IzinService::class)->ajukan(
                pegawai: $pegawai,
                jenis: $data['jenis'],
                tanggalMulai: $data['tanggal_mulai'],
                tanggalSelesai: $data['tanggal_selesai'],
                alasan: $data['alasan'] ?? null,
                sesiPerTanggal: $sesiPerTanggal,
                pengaju: auth()->user(),
            );
        } catch (SdmException $e) {
            Notification::make()->title('Gagal mengajukan izin')->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }
}
