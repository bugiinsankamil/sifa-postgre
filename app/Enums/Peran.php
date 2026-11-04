<?php

namespace App\Enums;

/**
 * Daftar peran aplikasi. Nilai (value) = nama peran di tabel roles (spatie/laravel-permission).
 */
enum Peran: string
{
    case Sysadmin = 'sysadmin';
    case Yayasan = 'yayasan';
    case Direktur = 'direktur';
    case KepalaCabang = 'kepala_cabang';
    case KepalaSekolah = 'kepala_sekolah';
    case WakilKepalaSekolah = 'wakil_kepala_sekolah';
    case PjProgram = 'pj_program';
    case Guru = 'guru';
    case Admin = 'admin';
    case StafServis = 'staf_servis';
    case OrangTua = 'orang_tua';
    case Siswa = 'siswa';

    public function label(): string
    {
        return match ($this) {
            self::Sysadmin => 'Sysadmin (administrator utama)',
            self::Yayasan => 'Yayasan',
            self::Direktur => 'Direktur',
            self::KepalaCabang => 'Kepala cabang',
            self::KepalaSekolah => 'Kepala sekolah',
            self::WakilKepalaSekolah => 'Wakil kepala sekolah',
            self::PjProgram => 'Penanggung jawab program',
            self::Guru => 'Guru',
            self::Admin => 'Admin',
            self::StafServis => 'Staf servis',
            self::OrangTua => 'Orang tua',
            self::Siswa => 'Siswa',
        };
    }

    /**
     * Peran tingkat yayasan: diberikan pada team 0 dan berlaku untuk seluruh cabang.
     * Peran lain diberikan per cabang (team = cabang_id).
     */
    public function berlakuGlobal(): bool
    {
        return in_array($this, [self::Sysadmin, self::Yayasan, self::Direktur], true);
    }

    /**
     * Lingkup kerja peran (informasi untuk logika akses, tidak dipaksakan oleh spatie).
     * 'sekolah' berarti peran diberikan per cabang, lalu dipersempit ke satuan pendidikan
     * lewat penugasan_pegawai.sekolah_id. 'pribadi' berarti hanya data milik sendiri
     * (siswa) atau anaknya (orang tua). 'sistem' berarti akses penuh ke seluruh aplikasi.
     */
    public function lingkup(): string
    {
        return match ($this) {
            self::Sysadmin => 'sistem',
            self::Yayasan, self::Direktur => 'yayasan',
            self::KepalaCabang, self::Admin, self::StafServis => 'cabang',
            self::KepalaSekolah, self::WakilKepalaSekolah, self::PjProgram, self::Guru => 'sekolah',
            self::OrangTua, self::Siswa => 'pribadi',
        };
    }

    /** @return string[] */
    public static function namaSemua(): array
    {
        return array_map(fn (self $peran) => $peran->value, self::cases());
    }
}
