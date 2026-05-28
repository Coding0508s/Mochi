<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentChangeRequest extends Model
{
    public const ORIGIN_LOCAL = 'A';

    public const ORIGIN_EXTERNAL = 'K';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'sk_code',
        'co',
        'tr',
        'cs',
        'origin',
        'status',
        'error_message',
        'requested_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }
}
