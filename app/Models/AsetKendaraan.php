<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsetKendaraan extends BaseModel
{
    protected $guarded = [];

    protected $primaryKey = 'aset_unit_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'masa_berlaku_stnk' => 'date',
            'masa_berlaku_pajak' => 'date',
        ];
    }

    public function asetUnit(): BelongsTo
    {
        return $this->belongsTo(AsetUnit::class, 'aset_unit_id');
    }
}
