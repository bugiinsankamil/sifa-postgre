<?php

namespace App\Events;

use App\Models\Pembayaran;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PembayaranBerhasil
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Pembayaran $pembayaran) {}
}
