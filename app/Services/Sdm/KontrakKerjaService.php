<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\KontrakKerja;
use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;

/**
 * Kontrak kerja: satu kontrak aktif per pegawai (dijaga partial unique index).
 * Mengakhiri kontrak lama dan membuat kontrak baru dilakukan dalam satu transaksi.
 */
class KontrakKerjaService
{
    public function buat(Pegawai $pegawai, array $data): KontrakKerja
    {
        $data += ['status' => 'aktif'];

        return DB::transaction(function () use ($pegawai, $data) {
            if ($data['status'] === 'aktif') {
                $adaAktif = KontrakKerja::query()
                    ->where('pegawai_id', $pegawai->id)
                    ->where('status', 'aktif')
                    ->exists();

                if ($adaAktif) {
                    throw new SdmException('Pegawai masih memiliki kontrak aktif. Akhiri kontrak lama terlebih dahulu.');
                }
            }

            return KontrakKerja::create(['pegawai_id' => $pegawai->id] + $data);
        });
    }

    /** Mengakhiri kontrak aktif dan langsung membuat kontrak baru (perpanjangan). */
    public function perpanjang(KontrakKerja $kontrak, array $dataBaru): KontrakKerja
    {
        return DB::transaction(function () use ($kontrak, $dataBaru) {
            $terkunci = KontrakKerja::query()->lockForUpdate()->findOrFail($kontrak->id);

            if ($terkunci->status !== 'aktif') {
                throw new SdmException('Hanya kontrak aktif yang dapat diperpanjang.');
            }

            $terkunci->update(['status' => 'diperpanjang']);

            return KontrakKerja::create([
                'pegawai_id' => $terkunci->pegawai_id,
                'status' => 'aktif',
            ] + $dataBaru);
        });
    }

    public function akhiri(KontrakKerja $kontrak, string $status = 'berakhir'): KontrakKerja
    {
        if (! in_array($status, ['berakhir', 'dibatalkan'], true)) {
            throw new SdmException('Status akhir kontrak harus "berakhir" atau "dibatalkan".');
        }

        return DB::transaction(function () use ($kontrak, $status) {
            $terkunci = KontrakKerja::query()->lockForUpdate()->findOrFail($kontrak->id);

            if ($terkunci->status !== 'aktif') {
                throw new SdmException('Hanya kontrak aktif yang dapat diakhiri.');
            }

            $terkunci->update(['status' => $status]);

            return $terkunci;
        });
    }

    /** Kontrak aktif yang akan berakhir dalam N hari ke depan (untuk peringatan scheduler). */
    public function akanBerakhir(int $hari = 30)
    {
        return KontrakKerja::query()
            ->with(['pegawai.orang'])
            ->where('status', 'aktif')
            ->whereNotNull('tanggal_selesai')
            ->whereBetween('tanggal_selesai', [today(), today()->addDays($hari)])
            ->orderBy('tanggal_selesai')
            ->get();
    }
}
