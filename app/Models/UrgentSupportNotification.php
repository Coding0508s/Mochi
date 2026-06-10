<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrgentSupportNotification extends Model
{
    protected $fillable = [
        'support_record_id',
        'recipient_user_id',
        'sender_user_id',
        'sk_code',
        'account_name',
        'message',
        'is_read',
        'read_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function supportRecord(): BelongsTo
    {
        return $this->belongsTo(SupportRecord::class, 'support_record_id', 'ID');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
