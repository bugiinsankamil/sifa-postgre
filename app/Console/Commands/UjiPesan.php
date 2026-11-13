<?php

namespace App\Console\Commands;

use App\Services\Pesan\PengelolaPesan;
use Illuminate\Console\Command;

class UjiPesan extends Command
{
    protected $signature = 'pesan:uji {tujuan : Nomor WhatsApp atau alamat email} {--kanal=whatsapp}';

    protected $description = 'Mengantrekan satu pesan uji untuk memeriksa jalur pengiriman (jalankan pesan:kirim sesudahnya)';

    public function handle(PengelolaPesan $pengelola): int
    {
        $pesan = $pengelola->antriLangsung(
            (string) $this->option('kanal'),
            (string) $this->argument('tujuan'),
            'Ini pesan uji dari sistem sekolah. Abaikan pesan ini.',
        );

        if ($pesan === null) {
            $this->error('Pesan tidak diantrekan (pesan dinonaktifkan atau tujuan kosong).');

            return self::FAILURE;
        }

        $this->info("Pesan uji diantrekan (id {$pesan->id}) untuk dikirim pada {$pesan->dijadwalkan_pada}. Jalankan: php artisan pesan:kirim");

        return self::SUCCESS;
    }
}
