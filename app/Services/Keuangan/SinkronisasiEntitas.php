<?php

namespace App\Services\Keuangan;

use App\Models\Cabang;
use App\Models\EntitasAkuntansi;
use App\Models\Sekolah;
use Illuminate\Support\Facades\DB;

/**
 * Menyelaraskan entitas akuntansi dari organisasi yang sudah ada.
 * Entitas yayasan dibuat bila belum ada; entitas cabang dan sekolah dibuat
 * otomatis dari setiap cabang/sekolah yang belum punya entitas.
 */
class SinkronisasiEntitas
{
    /**
     * Jalankan sinkronisasi: yayasan -> cabang -> sekolah.
     *
     * @return array{yayasan: int, cabang: int, sekolah: int} ID entitas yang dibuat/ditemukan
     */
    public function jalankan(): array
    {
        return DB::transaction(function () {
            $yayasan = $this->entitasYayasan();
            $cabangIds = $this->entitasCabang($yayasan->id);
            $sekolahIds = $this->entitasSekolah($cabangIds);

            return [
                'yayasan' => $yayasan->id,
                'cabang' => $cabangIds,
                'sekolah' => $sekolahIds,
            ];
        });
    }

    /** Entitas yayasan (root). Dibuat bila belum ada. */
    public function entitasYayasan(): EntitasAkuntansi
    {
        $yayasan = EntitasAkuntansi::query()
            ->withoutGlobalScopes()
            ->where('tipe', 'yayasan')
            ->whereNull('cabang_id')
            ->first();

        if ($yayasan) {
            return $yayasan;
        }

        return EntitasAkuntansi::create([
            'tipe' => 'yayasan',
            'kode' => 'YY',
            'nama' => 'Yayasan',
            'is_aktif' => true,
        ]);
    }

    /** @return int[] ID entitas cabang yang sudah dibuat/ditemukan */
    private function entitasCabang(int $yayasanId): array
    {
        $ids = [];
        $cabangList = Cabang::query()->withoutGlobalScopes()->orderBy('id')->get();

        foreach ($cabangList as $cabang) {
            $entitas = EntitasAkuntansi::query()
                ->withoutGlobalScopes()
                ->where('tipe', 'cabang')
                ->where('cabang_id', $cabang->id)
                ->first();

            if (!$entitas) {
                $entitas = EntitasAkuntansi::create([
                    'induk_id' => $yayasanId,
                    'tipe' => 'cabang',
                    'cabang_id' => $cabang->id,
                    'kode' => $cabang->kode,
                    'nama' => $cabang->nama,
                    'is_aktif' => $cabang->status === 'aktif',
                ]);
            }

            $ids[$cabang->id] = $entitas->id;
        }

        return $ids;
    }

    /** @return int[] ID entitas sekolah yang sudah dibuat/ditemukan */
    private function entitasSekolah(array $cabangEntitasIds): array
    {
        $ids = [];
        $sekolahList = Sekolah::query()->withoutGlobalScopes()->orderBy('id')->get();

        foreach ($sekolahList as $sekolah) {
            $entitas = EntitasAkuntansi::query()
                ->withoutGlobalScopes()
                ->where('tipe', 'sekolah')
                ->where('sekolah_id', $sekolah->id)
                ->first();

            if (!$entitas) {
                $indukId = $cabangEntitasIds[$sekolah->cabang_id] ?? null;
                if (!$indukId) {
                    continue;
                }

                $kodeSekolah = $sekolah->kode ?? 'S' . $sekolah->id;
                $entitas = EntitasAkuntansi::create([
                    'induk_id' => $indukId,
                    'tipe' => 'sekolah',
                    'cabang_id' => $sekolah->cabang_id,
                    'sekolah_id' => $sekolah->id,
                    'kode' => $kodeSekolah,
                    'nama' => $sekolah->nama,
                    'is_aktif' => $sekolah->status === 'aktif',
                ]);
            }

            $ids[] = $entitas->id;
        }

        return $ids;
    }
}
