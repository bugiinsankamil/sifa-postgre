<?php

namespace App\Events;

use App\Models\TransaksiKas;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransaksiKasDicatat
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TransaksiKas $transaksi) {}
}
