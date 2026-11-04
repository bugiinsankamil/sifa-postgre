<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Induk semua model aplikasi.
 *
 * Eloquent menebak nama tabel dengan mem-plural-kan nama class memakai aturan bahasa Inggris
 * (Siswa => siswas). Skema ini memakai nama tabel tunggal berbahasa Indonesia, jadi nama
 * tabel di sini = nama class dalam snake_case (Siswa => siswa, RombelSiswa => rombel_siswa).
 * Kunci asing (siswa_id) dan nama tabel pivot tetap mengikuti konvensi Laravel.
 */
abstract class BaseModel extends Model
{
    public function getTable(): string
    {
        return $this->table ?? Str::snake(class_basename($this));
    }
}
