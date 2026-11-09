<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use App\Support\Telepon;

class SpmbKontak extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kontak_pertama_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Nomor dinormalkan sebelum disimpan karena unique index memakai nilai telepon apa adanya
        static::saving(function (self $kontak) {
            $kontak->telepon = Telepon::normal($kontak->telepon);
            $kontak->whatsapp = Telepon::normal($kontak->whatsapp);
        });
    }
}
