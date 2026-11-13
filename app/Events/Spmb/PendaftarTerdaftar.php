<?php

namespace App\Events\Spmb;

use App\Models\SpmbPendaftar;
use Illuminate\Foundation\Events\Dispatchable;

class PendaftarTerdaftar
{
    use Dispatchable;

    public function __construct(public readonly SpmbPendaftar $pendaftar)
    {
    }
}
