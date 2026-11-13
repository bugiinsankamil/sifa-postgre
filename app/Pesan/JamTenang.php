<?php

namespace App\Pesan;

use Carbon\CarbonInterface;

/** Mengundurkan waktu kirim yang jatuh pada jam tenang (config pesan.jam_tenang) ke akhir jam tenang. */
final class JamTenang
{
    public static function sesuaikan(CarbonInterface $waktu): CarbonInterface
    {
        $mulai = config('pesan.jam_tenang.mulai');
        $selesai = config('pesan.jam_tenang.selesai');

        if (blank($mulai) || blank($selesai)) {
            return $waktu;
        }

        $awal = $waktu->copy()->setTimeFromTimeString($mulai);
        $akhir = $waktu->copy()->setTimeFromTimeString($selesai);

        if ($awal->gt($akhir)) {
            // Rentang melewati tengah malam, mis. 21:00 sampai 06:00
            if ($waktu->gte($awal)) {
                return $akhir->addDay();
            }
            if ($waktu->lt($akhir)) {
                return $akhir;
            }

            return $waktu;
        }

        return ($waktu->gte($awal) && $waktu->lt($akhir)) ? $akhir : $waktu;
    }
}
