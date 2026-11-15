<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class PenyusutanAset extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nilai_penyusutan' => 'decimal:2',
            'akumulasi' => 'decimal:2',
            'nilai_buku' => 'decimal:2',
        ];
    }

    public function aset(): MorphTo
    {
        return $this->morphTo();
    }
}
