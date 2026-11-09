<?php

namespace App\Models;

use App\Models\Concerns\MemilikiNikTerenkripsi;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orang extends BaseModel
{
    use MemilikiNikTerenkripsi, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
        ];
    }
}
