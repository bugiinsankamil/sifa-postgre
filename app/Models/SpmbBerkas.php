<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;

class SpmbBerkas extends BaseModel
{
    protected $guarded = [];

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiran');
    }
}
