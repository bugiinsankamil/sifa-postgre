<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lampiran extends BaseModel
{
    use SoftDeletes;

    protected $guarded = [];

    public function lampiran(): MorphTo
    {
        return $this->morphTo();
    }
}
