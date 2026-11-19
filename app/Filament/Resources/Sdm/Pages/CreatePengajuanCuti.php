<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Exceptions\Sdm\SdmException;
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
        $pegawai = PegawaiResource::getEloquentQuery()->findOrFail($data['pegawai_id']);
        $jenis = JenisCuti::findOrFail($data['jenis_cuti_id']);
        $pengganti = filled($data['pengganti_id'] ?? null)
            ? PegawaiResource::getEloquentQuery()->findOrFail($data['pengganti_id'])
            : null;

        $sesiPerTanggal = [];
        foreach (($data['hari_sesi'] ?? []) as $baris) {
            if (filled($baris['tanggal'] ?? null) && filled($baris['sesi'] ?? null)) {
                $sesiPerTanggal[$baris['tanggal']] = $baris['sesi'];
            }
        }

        try {
            return app(CutiService::class)->ajukan(
                pegawai: $pegawai,
                jenis: $jenis,
                tanggalMulai: $data['tanggal_mulai'],
                tanggalSelesai: $data['tanggal_selesai'],
                alasan: $data['alasan'] ?? null,
                pengganti: $pengganti,
                sesiPerTanggal: $sesiPerTanggal,
                pengaju: auth()->user(),
            );
        } catch (SdmException $e) {
            Notification::make()->title('Gagal mengajukan cuti')->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }
}
