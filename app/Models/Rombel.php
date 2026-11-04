<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;

/**
 * Contoh model yang dibatasi per cabang. Tambahkan trait BerLingkupCabang pada semua model
 * yang tabelnya memuat kolom cabang_id (sekolah, rombel, rombel_siswa, penugasan_pegawai, ...).
 */
class Rombel extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];
}
