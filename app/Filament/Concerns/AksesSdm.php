<?php

namespace App\Filament\Concerns;

use App\Support\CabangKonteks;
use Illuminate\Database\Eloquent\Model;

/**
 * Hak akses resource Kepegawaian (SDM) di panel admin.
 * Lihat: peran_lihat; Kelola master/data: peran_kelola; Status kepegawaian: peran_status.
 */
trait AksesSdm
{
    public static function canViewAny(): bool
    {
        return static::punyaPeranSdm(config('sdm.peran_lihat', []));
    }

    public static function canCreate(): bool
    {
        return static::punyaPeranSdm(config('sdm.peran_kelola', []));
    }

    public static function canView(Model $record): bool
    {
        return static::punyaPeranSdm(config('sdm.peran_lihat', []));
    }

    public static function canEdit(Model $record): bool
    {
        return static::punyaPeranSdm(config('sdm.peran_kelola', []));
    }

    public static function canDelete(Model $record): bool
    {
        return static::punyaPeranSdm(config('sdm.peran_kelola', []));
    }

    public static function canDeleteAny(): bool
    {
        return static::punyaPeranSdm(config('sdm.peran_kelola', []));
    }

    /** Apakah pengguna boleh mengubah status kepegawaian / mengakhiri kontrak. */
    public static function canUbahStatus(): bool
    {
        return static::punyaPeranSdm(config('sdm.peran_status', []));
    }

    private static function punyaPeranSdm(array $peran): bool
    {
        return app(CabangKonteks::class)->punyaPeran($peran);
    }
}
