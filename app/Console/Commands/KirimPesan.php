<?php

namespace App\Console\Commands;

use App\Models\AntreanPesan;
use App\Pesan\HasilKirim;
use App\Pesan\JamTenang;
use App\Services\Pesan\PengelolaPesan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class KirimPesan extends Command
{
    protected $signature = 'pesan:kirim {--batas= : Jumlah maksimum pesan pada eksekusi ini}';

    protected $description = 'Mengirim pesan yang sudah waktunya dari antrean_pesan';

    public function handle(PengelolaPesan $pengelola): int
    {
        if (! config('pesan.aktif', true)) {
            $this->warn('Pengiriman pesan dinonaktifkan (PESAN_AKTIF=false).');

            return self::SUCCESS;
        }

        // Pesan yang tertahan di status diproses terlalu lama (proses sebelumnya mati) diantrekan kembali
        AntreanPesan::query()->withoutGlobalScopes()
            ->where('status', 'diproses')
            ->where('diproses_pada', '<', now()->subMinutes((int) config('pesan.proses_macet_menit', 10)))
            ->update(['status' => 'antri']);

        $batas = (int) ($this->option('batas') ?: config('pesan.batas_per_proses', 20));

        // Mengambil dan menandai sebagai diproses dalam satu transaksi; SKIP LOCKED membuat dua proses
        // yang berjalan bersamaan tidak mengambil pesan yang sama.
        $ids = DB::transaction(function () use ($batas) {
            $ids = DB::table('antrean_pesan')
                ->where('status', 'antri')
                ->where('dijadwalkan_pada', '<=', now())
                ->orderBy('dijadwalkan_pada')->orderBy('id')
                ->limit($batas)
                ->lock('for update skip locked')
                ->pluck('id');

            DB::table('antrean_pesan')->whereIn('id', $ids)->update(['status' => 'diproses', 'diproses_pada' => now()]);

            return $ids;
        });

        if ($ids->isEmpty()) {
            return self::SUCCESS;
        }

        $jedaMs = (int) config('pesan.jeda_ms', 1500);
        $terkirim = 0;
        $gagal = 0;

        foreach ($ids->values() as $urutan => $id) {
            if ($urutan > 0 && $jedaMs > 0) {
                usleep($jedaMs * 1000);
            }

            $pesan = AntreanPesan::query()->withoutGlobalScopes()->find($id);
            $this->proses($pesan, $pengelola) ? $terkirim++ : $gagal++;
        }

        $this->line("Pesan diproses: {$terkirim} terkirim, {$gagal} belum terkirim.");

        return self::SUCCESS;
    }

    private function proses(AntreanPesan $pesan, PengelolaPesan $pengelola): bool
    {
        try {
            $hasil = $pengelola->pengirim($pesan->kanal)->kirim($pesan->tujuan, $pesan->isi, $pesan->judul);
        } catch (Throwable $e) {
            $hasil = HasilKirim::gagal($e::class.': '.$e->getMessage());
        }

        $percobaan = $pesan->percobaan + 1;

        if ($hasil->berhasil) {
            $pesan->update([
                'status' => 'terkirim',
                'percobaan' => $percobaan,
                'dikirim_pada' => now(),
                'respons' => ['id' => $hasil->idEksternal] + $hasil->respons,
                'galat' => null,
            ]);

            return true;
        }

        $maks = (int) config('pesan.maks_percobaan', 5);

        if ($hasil->bisaDiulang && $percobaan < $maks) {
            $backoff = (array) config('pesan.backoff_menit', [1, 5, 15, 60, 180]);
            $menit = $backoff[min($percobaan - 1, count($backoff) - 1)] ?? 5;

            $pesan->update([
                'status' => 'antri',
                'percobaan' => $percobaan,
                'dijadwalkan_pada' => JamTenang::sesuaikan(now()->addMinutes($menit)),
                'respons' => $hasil->respons ?: null,
                'galat' => $hasil->galat,
            ]);
        } else {
            $pesan->update([
                'status' => 'gagal',
                'percobaan' => $percobaan,
                'respons' => $hasil->respons ?: null,
                'galat' => $hasil->galat,
            ]);
        }

        return false;
    }
}
