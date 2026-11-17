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
    protected $signature = 'absensi:proses-log {--limit= : Batasi jumlah log yang diproses}';
    protected $description = 'Proses log mentah absensi menjadi absensi harian';

    public function handle(AbsensiLogProcessor $processor): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $jumlah = $processor->prosesSemuaLogMentah($limit);

        $this->info('Berhasil memproses '.$jumlah.' log absensi.');

        return self::SUCCESS;
    }
}
