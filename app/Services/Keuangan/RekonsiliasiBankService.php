<?php

namespace App\Services\Keuangan;

use App\Models\RekeningKas;
use App\Models\RekonsiliasiBank;
use App\Models\TransaksiKas;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RekonsiliasiBankService
{
    public function mulai(int $rekeningId, int $tahun, int $bulan, string $saldoBank): RekonsiliasiBank
    {
        return DB::transaction(function () use ($rekeningId, $tahun, $bulan, $saldoBank) {
            $rekening = RekeningKas::query()->where('id', $rekeningId)->where('tipe', 'bank')->first();
            if (!$rekening) {
                throw new RuntimeException('Rekening bank tidak ditemukan.');
            }

            $existing = RekonsiliasiBank::query()
                ->where('rekening_id', $rekeningId)
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->first();
            if ($existing && $existing->status === 'selesai') {
                throw new RuntimeException('Rekonsiliasi bulan ini sudah selesai.');
            }

            $rekonsiliasi = RekonsiliasiBank::updateOrCreate(
                ['rekening_id' => $rekeningId, 'tahun' => $tahun, 'bulan' => $bulan],
                ['saldo_bank' => $saldoBank, 'saldo_buku' => '0', 'selisih' => '0', 'status' => 'belum']
            );

            return $rekonsiliasi->fresh();
        });
    }

    public function selesaikan(RekonsiliasiBank $rekonsiliasi, int $userId): RekonsiliasiBank
    {
        return DB::transaction(function () use ($rekonsiliasi, $userId) {
            $terkunci = RekonsiliasiBank::query()->lockForUpdate()->findOrFail($rekonsiliasi->id);

            if ($terkunci->status === 'selesai') {
                throw new RuntimeException('Rekonsiliasi sudah selesai.');
            }

            $saldoBuku = $this->hitungSaldoBuku($terkunci->rekening_id, $terkunci->tahun, $terkunci->bulan);
            $selisih = bcsub((string) $terkunci->saldo_bank, (string) $saldoBuku, 2);

            $terkunci->update([
                'saldo_buku' => $saldoBuku,
                'selisih' => $selisih,
                'status' => 'selesai',
            ]);

            return $terkunci->fresh();
        });
    }

    private function hitungSaldoBuku(int $rekeningId, int $tahun, int $bulan): string
    {
        $awal = "$tahun-$bulan-01";
        $akhir = (new \DateTime("$tahun-$bulan-01"))->modify('last day of this month')->format('Y-m-d');

        $masuk = TransaksiKas::query()
            ->where('rekening_id', $rekeningId)
            ->whereIn('tipe', ['masuk', 'transfer'])
            ->where('status', 'posted')
            ->whereBetween('tanggal', [$awal, $akhir])
            ->where(function ($q) use ($rekeningId) {
                $q->where('rekening_tujuan_id', $rekeningId)
                    ->orWhere(function ($q2) use ($rekeningId) {
                        $q2->where('rekening_id', $rekeningId)->where('tipe', 'masuk');
                    });
            })
            ->sum('nominal');

        $keluar = TransaksiKas::query()
            ->where('rekening_id', $rekeningId)
            ->whereIn('tipe', ['keluar', 'transfer'])
            ->where('status', 'posted')
            ->whereBetween('tanggal', [$awal, $akhir])
            ->sum('nominal');

        return bcsub(bcadd((string) $masuk, '0', 2), bcadd((string) $keluar, '0', 2), 2);
    }
}
