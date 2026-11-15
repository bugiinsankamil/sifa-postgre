<?php

namespace App\Filament\Resources\Sdm\Pages;

use App\Filament\Resources\Sdm\PengajuanCutiResource;
use App\Models\JenisCuti;
use App\Models\Pegawai;
use App\Services\Sdm\CutiService;
use Filament\Resources\Pages\CreateRecord;

class CreatePengajuanCuti extends CreateRecord
{
    protected static string $resource = PengajuanCutiResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $pegawai = Pegawai::findOrFail($data['pegawai_id']);
        $jenis = JenisCuti::findOrFail($data['jenis_cuti_id']);
        $pengganti = isset($data['pengganti_id']) ? Pegawai::find($data['pengganti_id']) : null;

        return app(CutiService::class)->ajukan(
            $pegawai,
            $jenis,
            $data['tanggal_mulai'],
            $data['tanggal_selesai'],
            (int) $data['jumlah_hari'],
            $data['alasan'] ?? null,
            $pengganti,
        );
    }
}
