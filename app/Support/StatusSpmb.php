<?php

namespace App\Support;

final class StatusSpmb
{
    public const LABEL = [
        'draft' => 'Draft',
        'terdaftar' => 'Menunggu verifikasi',
        'diverifikasi' => 'Terverifikasi',
        'seleksi' => 'Dalam seleksi',
        'lulus' => 'Lulus',
        'cadangan' => 'Cadangan',
        'tidak_lulus' => 'Tidak lulus',
        'daftar_ulang' => 'Daftar ulang',
        'mundur' => 'Mundur',
        'menjadi_siswa' => 'Menjadi siswa',
    ];

    public static function label(?string $status): string
    {
        return self::LABEL[$status] ?? (string) $status;
    }

    public static function warna(?string $status): string
    {
        return match ($status) {
            'terdaftar' => 'warning',
            'diverifikasi', 'seleksi' => 'info',
            'lulus', 'menjadi_siswa' => 'success',
            'cadangan' => 'warning',
            'tidak_lulus' => 'danger',
            'daftar_ulang' => 'primary',
            default => 'gray',
        };
    }
}
