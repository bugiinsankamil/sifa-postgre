<?php

namespace App\Pesan;

/** Hasil satu percobaan pengiriman pesan. */
final class HasilKirim
{
    private function __construct(
        public readonly bool $berhasil,
        public readonly ?string $idEksternal = null,
        public readonly array $respons = [],
        public readonly ?string $galat = null,
        public readonly bool $bisaDiulang = true,
    ) {
    }

    public static function sukses(?string $idEksternal = null, array $respons = []): self
    {
        return new self(true, $idEksternal, $respons);
    }

    /** @param bool $bisaDiulang false untuk kegagalan permanen (nomor tidak valid, ditolak penyedia, dst.) */
    public static function gagal(string $galat, bool $bisaDiulang = true, array $respons = []): self
    {
        return new self(false, null, $respons, $galat, $bisaDiulang);
    }
}
