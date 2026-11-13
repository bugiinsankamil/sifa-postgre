<?php

namespace App\Filament\Concerns;

use App\Support\CabangKonteks;
use Illuminate\Database\Eloquent\Model;

/** Hak akses resource antrean pesan (hanya lihat dan kelola antrean; tidak membuat atau menghapus). */
trait AksesPesan
{
    public static function canViewAny(): bool
    {
        return app(CabangKonteks::class)->punyaPeran(config('pesan.peran_lihat', []));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
