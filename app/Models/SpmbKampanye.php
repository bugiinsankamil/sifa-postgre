<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpmbKampanye extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_akhir' => 'date',
            'anggaran' => 'decimal:2',
        ];
    }

    public function kontak(): HasMany
    {
        return $this->hasMany(SpmbKontak::class, 'kampanye_id');
    }
}
