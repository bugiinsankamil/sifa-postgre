<?php

namespace App\Events;

use App\Models\KeputusanKenaikan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KenaikanKelasDitetapkan
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public KeputusanKenaikan $keputusan) {}
}
