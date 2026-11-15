<?php

namespace App\Services\Inventaris;

use App\Models\MutasiStok;
use App\Models\StokBarang;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengelola stok barang habis pakai. Semua perubahan stok harus melalui mutasi_stok.
 */
class TransaksiStok
{
    /**
     * Menambah stok barang di gudang.
     */
    public function mutasi(
        int $gudangId,
        int $barangId,
        int $jumlah,
        string $tipe,
        ?float $hargaSatuan,
        string $sumberType,
        int $sumberId,
        int $userId
    ): MutasiStok {
        if ($jumlah === 0) {
            throw new RuntimeException('Jumlah mutasi stok tidak boleh nol.');
        }

        $tipeKeluar = in_array($tipe, ['keluar', 'transfer_keluar', 'penyesuaian']);
        if (!$tipeKeluar && $jumlah < 0) {
            throw new RuntimeException('Jumlah negatif hanya diizinkan untuk mutasi keluar, transfer keluar, atau penyesuaian.');
        }

        return DB::transaction(function () use ($gudangId, $barangId, $jumlah, $tipe, $hargaSatuan, $sumberType, $sumberId, $userId) {
            $stok = StokBarang::query()
                ->where('gudang_id', $gudangId)
                ->where('barang_id', $barangId)
                ->lockForUpdate()
                ->first();

            if (!$stok) {
                $stok = StokBarang::create([
                    'gudang_id' => $gudangId,
                    'barang_id' => $barangId,
                    'jumlah' => 0,
                ]);
            }

            $saldoBaru = $stok->jumlah + $jumlah;

            if ($saldoBaru < 0) {
                throw new RuntimeException("Stok tidak mencukupi. Saldo saat ini: {$stok->jumlah}, diminta keluar: " . abs($jumlah));
            }

            $stok->update(['jumlah' => $saldoBaru]);

            $mutasi = MutasiStok::create([
                'gudang_id' => $gudangId,
                'barang_id' => $barangId,
                'tanggal' => now(),
                'tipe' => $tipe,
                'jumlah' => $jumlah,
                'harga_satuan' => $hargaSatuan,
                'saldo_setelah' => $saldoBaru,
                'sumber_type' => $sumberType,
                'sumber_id' => $sumberId,
                'dibuat_oleh' => $userId,
            ]);

            return $mutasi;
        });
    }

    /**
     * Mendapatkan saldo stok terkini untuk barang di gudang.
     */
    public function saldo(int $gudangId, int $barangId): int
    {
        $stok = StokBarang::query()
            ->where('gudang_id', $gudangId)
            ->where('barang_id', $barangId)
            ->first();

        return $stok?->jumlah ?? 0;
    }

    /**
     * Transfer stok antar gudang (dalam satu cabang atau lintas cabang).
     */
    public function transfer(
        int $gudangAsalId,
        int $gudangTujuanId,
        int $barangId,
        int $jumlah,
        string $sumberType,
        int $sumberId,
        int $userId
    ): array {
        if ($jumlah <= 0) {
            throw new RuntimeException('Jumlah transfer harus positif.');
        }

        return DB::transaction(function () use ($gudangAsalId, $gudangTujuanId, $barangId, $jumlah, $sumberType, $sumberId, $userId) {
            $keluar = $this->mutasi(
                $gudangAsalId,
                $barangId,
                -$jumlah,
                'transfer_keluar',
                null,
                $sumberType,
                $sumberId,
                $userId
            );

            $masuk = $this->mutasi(
                $gudangTujuanId,
                $barangId,
                $jumlah,
                'transfer_masuk',
                null,
                $sumberType,
                $sumberId,
                $userId
            );

            return ['keluar' => $keluar, 'masuk' => $masuk];
        });
    }

    /**
     * Mengecek apakah stok barang di gudang mencukupi.
     */
    public function cukup(int $gudangId, int $barangId, int $jumlah): bool
    {
        return $this->saldo($gudangId, $barangId) >= $jumlah;
    }

    /**
     * Mendapatkan daftar barang yang stoknya di bawah minimum.
     */
    public function barangDiBawahMinimum(int $gudangId): array
    {
        $stok = StokBarang::query()
            ->join('barang', 'stok_barang.barang_id', '=', 'barang.id')
            ->where('stok_barang.gudang_id', $gudangId)
            ->whereNotNull('barang.stok_minimum')
            ->whereColumn('stok_barang.jumlah', '<', 'barang.stok_minimum')
            ->select([
                'barang.id as barang_id',
                'stok_barang.gudang_id',
                'stok_barang.jumlah as stok',
                'barang.stok_minimum as minimum',
            ])
            ->get();

        return $stok->toArray();
    }
}
