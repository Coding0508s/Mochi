<?php

namespace App\Policies;

use App\Models\TeamSchedule;
use App\Models\User;

class TeamSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TeamSchedule $teamSchedule): bool
    {
        if ($user->hasFullAccess() || $teamSchedule->user_id === $user->id) {
            return true;
        }

        return $teamSchedule->visibility === 'team'
            && filled($user->team)
            && $teamSchedule->user?->team === $user->team;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TeamSchedule $teamSchedule): bool
    {
        return $user->hasFullAccess() || $teamSchedule->user_id === $user->id;
    }

    public function delete(User $user, TeamSchedule $teamSchedule): bool
    {
        return $this->update($user, $teamSchedule);
    }
}
