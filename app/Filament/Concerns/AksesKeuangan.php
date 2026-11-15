<?php

namespace App\Filament\Concerns;

use App\Support\CabangKonteks;
use Illuminate\Database\Eloquent\Model;

/**
 * Hak akses resource Keuangan di panel admin.
 * Lihat: semua peran keuangan; Draft: admin+kepala cabang; Posting: kepala cabang+global.
 */
trait AksesKeuangan
{
    public static function canViewAny(): bool
    {
        return static::punyaPeranKeuangan(config('keuangan.peran_lihat', []));
    }

    public static function canCreate(): bool
    {
        return static::punyaPeranKeuangan(config('keuangan.peran_draft', []));
    }

    public static function canView(Model $record): bool
    {
        return static::punyaPeranKeuangan(config('keuangan.peran_lihat', []));
    }

    public static function canEdit(Model $record): bool
    {
        // Hanya jurnal draft yang bisa diedit
        if ($record->status !== 'draft') {
            return false;
        }

        return static::punyaPeranKeuangan(config('keuangan.peran_draft', []));
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Jurnal tidak boleh dihapus; void lewat service
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Apakah pengguna bisa memposting jurnal di cabang/entitas ini. */
    public static function canPost(Model $record): bool
    {
        return static::punyaPeranKeuangan(config('keuangan.peran_posting', []));
    }

    /** Apakah pengguna bisa mengelola master (akun, entitas, pusat biaya). */
    public static function canManageMaster(): bool
    {
        return static::punyaPeranKeuangan(config('keuangan.peran_master', []));
    }

    /** Apakah pengguna bisa menutup periode. */
    public static function canTutupPeriode(): bool
    {
        return static::punyaPeranKeuangan(config('keuangan.peran_tutup_periode', []));
    }

    private static function punyaPeranKeuangan(array $peran): bool
    {
        return app(CabangKonteks::class)->punyaPeran($peran);
    }
}
