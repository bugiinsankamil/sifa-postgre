<?php

namespace App\Events\Spmb;

use App\Models\SpmbBerkas;
use Illuminate\Foundation\Events\Dispatchable;

class BerkasDitolak
{
    use Dispatchable;

    public function __construct(public readonly SpmbBerkas $berkas)
    {
    }
}
