<?php

namespace App\Services\Inventaris;

use App\Models\AsetUnit;
use App\Models\MutasiAset;
use App\Models\MutasiAsetBaris;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengelola mutasi (perpindahan) aset antar ruang dalam satu cabang atau antar cabang.
 */
class MutasiAsetService
{
    /**
     * Membuat pengajuan mutasi aset.
     *
     * @param array $data Data mutasi (cabang_asal_id, cabang_tujuan_id, ruang_tujuan_id, tanggal, alasan)
     * @param array<int, array{aset_unit_id: int, ruang_asal_id?: int, kondisi: string}> $baris Aset yang dimutasi
     */
    public function ajukan(array $data, array $baris): MutasiAset
    {
        if (empty($baris)) {
            throw new RuntimeException('Mutasi aset harus memiliki minimal satu aset.');
        }

        return DB::transaction(function () use ($data, $baris) {
            $mutasi = MutasiAset::create(array_merge($data, [
                'status' => 'diajukan',
            ]));

            foreach ($baris as $b) {
                MutasiAsetBaris::create([
                    'mutasi_id' => $mutasi->id,
                    'aset_unit_id' => $b['aset_unit_id'],
                    'ruang_asal_id' => $b['ruang_asal_id'] ?? null,
                    'kondisi' => $b['kondisi'],
                ]);
            }

            return $mutasi->fresh(['baris']);
        });
    }

    /**
     * Menyetujui mutasi aset.
     */
    public function setujui(MutasiAset $mutasi): MutasiAset
    {
        if ($mutasi->status !== 'diajukan') {
            throw new RuntimeException('Hanya mutasi dengan status diajukan yang dapat disetujui.');
        }

        $mutasi->update(['status' => 'disetujui']);

        return $mutasi;
    }

    /**
     * Menyelesaikan mutasi. Aset unit dipindahkan ke cabang/ruang tujuan.
     */
    public function selesaikan(MutasiAset $mutasi): MutasiAset
    {
        if ($mutasi->status !== 'disetujui') {
            throw new RuntimeException('Hanya mutasi dengan status disetujui yang dapat diselesaikan.');
        }

        return DB::transaction(function () use ($mutasi) {
            // Update setiap aset unit ke cabang/ruang tujuan
            foreach ($mutasi->baris as $baris) {
                AsetUnit::where('id', $baris->aset_unit_id)->update([
                    'cabang_id' => $mutasi->cabang_tujuan_id,
                    'ruang_id' => $mutasi->ruang_tujuan_id,
                ]);
            }

            $mutasi->update(['status' => 'selesai']);

            return $mutasi->fresh(['baris']);
        });
    }

    /**
     * Membatalkan mutasi.
     */
    public function batalkan(MutasiAset $mutasi, string $alasan): MutasiAset
    {
        if (!in_array($mutasi->status, ['diajukan', 'disetujui'])) {
            throw new RuntimeException('Mutasi tidak dapat dibatalkan pada status ini.');
        }

        $mutasi->update([
            'status' => 'batal',
            'alasan' => $mutasi->alasan . "\nDibatalkan: {$alasan}",
        ]);

        return $mutasi;
    }
}
