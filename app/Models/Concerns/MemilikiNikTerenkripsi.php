<?php

namespace App\Models\Concerns;

/**
 * Untuk model yang punya kolom nik (terenkripsi) dan nik_hash (HMAC-SHA256, unik).
 * Unik pada kolom terenkripsi tidak berguna karena hasil enkripsi selalu berbeda,
 * jadi cek duplikat dan pencarian memakai nik_hash.
 */
trait MemilikiNikTerenkripsi
{
    public static function bootMemilikiNikTerenkripsi(): void
    {
        static::saving(function ($model) {
            if (! $model->isDirty('nik')) {
                return;
            }

            $nik = $model->nik !== null ? preg_replace('/\D+/', '', (string) $model->nik) : null;
            $nik = $nik !== '' ? $nik : null;

            $model->nik = $nik;
            $model->nik_hash = $nik !== null ? static::hashNik($nik) : null;
        });
    }

    public function initializeMemilikiNikTerenkripsi(): void
    {
        $this->mergeCasts(['nik' => 'encrypted']);
    }

    public static function hashNik(string $nik): string
    {
        // Disarankan memakai kunci khusus (NIK_HASH_KEY) agar rotasi APP_KEY tidak merusak hash.
        $kunci = config('app.nik_hash_key', config('app.key'));

        return hash_hmac('sha256', $nik, (string) $kunci);
    }

    public static function cariNik(string $nik)
    {
        return static::query()->where('nik_hash', static::hashNik(preg_replace('/\D+/', '', $nik)));
    }
}
