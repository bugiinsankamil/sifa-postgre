<?php

namespace Tests\Support;

use App\Pesan\HasilKirim;
use App\Pesan\Pengirim;
use RuntimeException;

/** Pengirim palsu untuk tes: mencatat pesan dan hasilnya bisa diatur. */
class PengirimUji implements Pengirim
{
    /** @var array<int, array{tujuan: string, isi: string, judul: ?string}> */
    public static array $terkirim = [];

    /** @var HasilKirim[] hasil berikutnya, diambil satu per pengiriman; kosong berarti sukses */
    public static array $hasilBerikut = [];

    public static ?string $lempar = null;

    public static function reset(): void
    {
        self::$terkirim = [];
        self::$hasilBerikut = [];
        self::$lempar = null;
    }

    public function kirim(string $tujuan, string $isi, ?string $judul = null): HasilKirim
    {
        if (self::$lempar !== null) {
            throw new RuntimeException(self::$lempar);
        }

        self::$terkirim[] = compact('tujuan', 'isi', 'judul');

        return array_shift(self::$hasilBerikut) ?? HasilKirim::sukses('uji-'.count(self::$terkirim));
    }
}
