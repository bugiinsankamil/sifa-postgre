<?php

namespace App\Support;

final class Telepon
{
    /**
     * Menormalkan nomor telepon Indonesia ke bentuk 62812xxxx supaya "0812-3456", "+62 812 3456",
     * dan "62812 3456" dianggap sama. Nomor di luar pola Indonesia hanya dibersihkan dari non-angka.
     */
    public static function normal(?string $nomor): ?string
    {
        if ($nomor === null) {
            return null;
        }

        $digit = preg_replace('/\D+/', '', $nomor);
        if ($digit === '' || $digit === null) {
            return null;
        }

        if (str_starts_with($digit, '62')) {
            return $digit;
        }
        if (str_starts_with($digit, '0')) {
            return '62'.substr($digit, 1);
        }
        if (str_starts_with($digit, '8')) {
            return '62'.$digit;
        }

        return $digit;
    }
}
