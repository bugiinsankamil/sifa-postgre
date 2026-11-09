<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Pembuat nomor dokumen berurutan memakai tabel penomoran.
 *
 * Token pola: {SEQ:n} nomor urut berpanjang n, {TAHUN}, {BULAN}, {BULAN_ROMAWI}, {CABANG} (kode cabang).
 * Contoh: 'SPMB/{CABANG}/{TAHUN}/{SEQ:4}' menghasilkan SPMB/FTN-01/2026/0001.
 *
 * Penghitung terpisah per (kode, cabang_id, tahun). Baris dikunci (SELECT ... FOR UPDATE) selama transaksi
 * sehingga dua permintaan bersamaan tidak mendapat nomor yang sama. Bila dipanggil di dalam transaksi
 * lain, nomor ikut batal jika transaksi itu dibatalkan (tidak ada lubang nomor).
 */
class PenomoranDokumen
{
    private const ROMAWI = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public function berikutnya(string $kode, ?int $cabangId = null, ?int $tahun = null, string $polaBawaan = '{SEQ:5}'): string
    {
        return DB::transaction(function () use ($kode, $cabangId, $tahun, $polaBawaan) {
            $baris = $this->kunci($kode, $cabangId, $tahun);

            if ($baris === null) {
                // Aman terhadap dua proses yang membuat baris bersamaan: ON CONFLICT DO NOTHING
                DB::table('penomoran')->insertOrIgnore([
                    'kode' => $kode,
                    'cabang_id' => $cabangId,
                    'tahun' => $tahun,
                    'pola' => $polaBawaan,
                    'nomor_terakhir' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $baris = $this->kunci($kode, $cabangId, $tahun);
            }

            $nomor = (int) $baris->nomor_terakhir + 1;

            DB::table('penomoran')->where('id', $baris->id)->update([
                'nomor_terakhir' => $nomor,
                'updated_at' => now(),
            ]);

            return $this->format($baris->pola, $nomor, $cabangId, $tahun);
        });
    }

    private function kunci(string $kode, ?int $cabangId, ?int $tahun): ?object
    {
        return DB::table('penomoran')
            ->where('kode', $kode)
            ->when($cabangId === null, fn ($q) => $q->whereNull('cabang_id'), fn ($q) => $q->where('cabang_id', $cabangId))
            ->when($tahun === null, fn ($q) => $q->whereNull('tahun'), fn ($q) => $q->where('tahun', $tahun))
            ->lockForUpdate()
            ->first();
    }

    private function format(string $pola, int $nomor, ?int $cabangId, ?int $tahun): string
    {
        $sekarang = now();

        return preg_replace_callback('/\{([A-Z_]+)(?::(\d+))?\}/', function (array $m) use ($nomor, $cabangId, $tahun, $sekarang) {
            return match ($m[1]) {
                'SEQ' => str_pad((string) $nomor, (int) ($m[2] ?? 5), '0', STR_PAD_LEFT),
                'TAHUN' => (string) ($tahun ?? $sekarang->year),
                'BULAN' => $sekarang->format('m'),
                'BULAN_ROMAWI' => self::ROMAWI[(int) $sekarang->format('n')],
                'CABANG' => $cabangId !== null ? (string) DB::table('cabang')->where('id', $cabangId)->value('kode') : '',
                default => $m[0],
            };
        }, $pola);
    }
}
