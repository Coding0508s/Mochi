<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'account_audit_logs';

    protected $fillable = [
        'user_id',
        'actor_id',
        'action',
        'changes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(User $user, ?User $actor, string $action, array $changes = []): void
    {
        static::query()->create([
            'user_id' => $user->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'changes' => $changes !== [] ? $changes : null,
        ]);
    }
}
