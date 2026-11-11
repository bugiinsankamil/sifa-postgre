<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Siswa extends BaseModel
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal_masuk_yayasan' => 'date'];
    }
}
