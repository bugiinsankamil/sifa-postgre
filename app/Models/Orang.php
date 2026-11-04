<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contoh model dengan kolom terenkripsi yang tetap bisa dicari dan dicek duplikatnya.
 *
 * nik       : terenkripsi (cast 'encrypted'), tidak bisa dicari langsung.
 * nik_hash  : HMAC-SHA256 dari NIK ternormalisasi, unik, dipakai untuk cek duplikat dan pencarian.
 */
class Orang extends BaseModel
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'tanggal_lahir' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $orang) {
            if (! $orang->isDirty('nik')) {
                return;
            }

            $nik = $orang->nik !== null ? preg_replace('/\D+/', '', (string) $orang->nik) : null;
            $nik = $nik !== '' ? $nik : null;

            $orang->nik = $nik;
            $orang->nik_hash = $nik !== null ? self::hashNik($nik) : null;
        });
    }

    public static function hashNik(string $nik): string
    {
        // Disarankan memakai kunci khusus (NIK_HASH_KEY) agar rotasi APP_KEY tidak merusak hash.
        $kunci = config('app.nik_hash_key', config('app.key'));

        return hash_hmac('sha256', $nik, (string) $kunci);
    }

    public static function cariNik(string $nik)
    {
        return static::query()->where('nik_hash', self::hashNik(preg_replace('/\D+/', '', $nik)));
    }
}
