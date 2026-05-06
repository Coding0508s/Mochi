<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalAssignmentInboundLog extends Model
{
    protected $fillable = [
        'sk_code',
        'co',
        'tr',
        'cs',
        'raw_body',
        'status',
        'error_message',
        'received_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_body' => 'array',
            'received_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }
}
