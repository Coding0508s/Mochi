<?php

namespace App\Policies;

use App\Models\SharedSupply;
use App\Models\User;

class SharedSupplyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SharedSupply $sharedSupply): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SharedSupply $sharedSupply): bool
    {
        return $user->hasFullAccess() || $sharedSupply->user_id === $user->id;
    }

    public function delete(User $user, SharedSupply $sharedSupply): bool
    {
        return $this->update($user, $sharedSupply);
    }
}
