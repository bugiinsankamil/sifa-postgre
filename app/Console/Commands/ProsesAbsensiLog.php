<?php

namespace App\Console\Commands;

use App\Services\Sdm\AbsensiLogProcessor;
use Illuminate\Console\Command;

/**
 * Proses log mentah absensi yang belum diproses.
 * Dipakai oleh scheduler untuk memastikan absensi harian terkini.
 */
class ProsesAbsensiLog extends Command
{
    protected $signature = 'absensi:proses-log {--limit=500 : Batasi jumlah log yang diproses}';
    protected $description = 'Proses log mentah absensi menjadi absensi harian';

    public function handle(AbsensiLogProcessor $processor): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1) {
            $limit = AbsensiLogProcessor::BATCH_BAWAAN;
        }

        $jumlah = $processor->prosesSemuaLogMentah($limit);

        $this->info('Berhasil memproses '.$jumlah.' log absensi.');
        if ($processor->gagal > 0) {
            $this->warn($processor->gagal.' log gagal diproses; lihat panel Log Absensi Gagal.');
        }

        return self::SUCCESS;
    }
}
