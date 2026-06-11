<?php

namespace App\Models;

use App\Support\SetupRoleAccountFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SetupRole extends Model
{
    protected $table = 'setup_roles';

    protected $fillable = [
        'role_key',
        'role_name',
        'description',
        'is_active',
        'permissions',
        'account_flags',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'permissions' => 'array',
            'account_flags' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'setup_role_id');
    }

    /**
     * @return array<string, bool>
     */
    public function normalizedAccountFlags(): array
    {
        return SetupRoleAccountFlags::normalize($this->account_flags);
    }

    public function syncAccountFlagsToAssignedUsers(): void
    {
        $flags = $this->normalizedAccountFlags();

        $this->users()->each(function (User $user) use ($flags): void {
            SetupRoleAccountFlags::applyToUser($user, $flags);
            $user->save();
        });
    }
}
