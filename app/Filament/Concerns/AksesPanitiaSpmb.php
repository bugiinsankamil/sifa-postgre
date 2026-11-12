<?php

namespace App\Filament\Concerns;

use App\Support\CabangKonteks;
use Illuminate\Database\Eloquent\Model;

/**
 * Hak akses resource SPMB di panel admin: panitia (config/spmb.php => peran_panitia) boleh melihat dan
 * mengubah; menghapus hanya sysadmin. Data tetap dibatasi ke cabang pengguna lewat global scope model.
 */
trait AksesPanitiaSpmb
{
    public static function canViewAny(): bool
    {
        return static::panitiaSpmb();
    }

    public static function canCreate(): bool
    {
        return static::panitiaSpmb();
    }

    public static function canView(Model $record): bool
    {
        return static::panitiaSpmb();
    }

    public static function canEdit(Model $record): bool
    {
        return static::panitiaSpmb();
    }

    public static function canDelete(Model $record): bool
    {
        return static::hanyaSysadmin();
    }

    public static function canDeleteAny(): bool
    {
        return static::hanyaSysadmin();
    }

    protected static function panitiaSpmb(): bool
    {
        return app(CabangKonteks::class)->punyaPeran(config('spmb.peran_panitia', []));
    }

    protected static function hanyaSysadmin(): bool
    {
        return app(CabangKonteks::class)->punyaPeran(['sysadmin']);
    }
}
