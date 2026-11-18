<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\PengajuanCutiResource;
use App\Filament\Resources\Sdm\PegawaiResource;
use App\Models\JenisCuti;
use App\Services\Sdm\CutiService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreatePengajuanCuti extends CreateRecord
{
    protected static string $resource = PengajuanCutiResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Pegawai dan pengganti hanya dari cabang yang berhak diakses pengguna
        $pegawai = PegawaiResource::getEloquentQuery()->findOrFail($data['pegawai_id']);
        $jenis = JenisCuti::findOrFail($data['jenis_cuti_id']);
        $pengganti = filled($data['pengganti_id'] ?? null)
            ? PegawaiResource::getEloquentQuery()->findOrFail($data['pengganti_id'])
            : null;

        try {
            return app(CutiService::class)->ajukan(
                $pegawai,
                $jenis,
                $data['tanggal_mulai'],
                $data['tanggal_selesai'],
                (int) $data['jumlah_hari'],
                $data['alasan'] ?? null,
                $pengganti,
            );
        } catch (\RuntimeException $e) {
            Notification::make()->title('Gagal mengajukan cuti')->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }
}
