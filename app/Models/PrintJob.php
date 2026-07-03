<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = [
        'uuid',
        'branch_id',
        'requested_by',
        'printer_name',
        'kind',
        'payload_base64',
        'status',
        'attempts',
        'last_error',
        'claimed_at',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }
}
