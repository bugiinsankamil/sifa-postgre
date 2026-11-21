<?php

namespace App\Models;

class PembayaranGatewayLog extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'diterima_pada' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
