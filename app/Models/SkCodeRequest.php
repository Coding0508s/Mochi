<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkCodeRequest extends Model
{
    protected $fillable = [
        'co_new_target_id',
        'institution_name',
        'temp_sk_code',
        'final_sk_code',
        'portal_campus_id',
        'account_no',
        'co',
        'tr',
        'cs',
        'status',
        'error_message',
        'requested_at',
        'completed_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function coNewTarget(): BelongsTo
    {
        return $this->belongsTo(CoNewTarget::class, 'co_new_target_id', 'ID');
    }
}
