<?php

namespace App\Livewire\Concerns;

use App\Support\TeamMenuContext;

trait GuardsCrossTeamReadOnlyContext
{
    protected function assertCanMutateInTeamContext(?string $teamMenuOverride = null): void
    {
        TeamMenuContext::abortIfCrossTeamReadOnly(auth()->user(), $teamMenuOverride);
    }

    protected function isCrossTeamReadOnlyContext(?string $teamMenuOverride = null): bool
    {
        return TeamMenuContext::isCrossTeamReadOnlyContext(auth()->user(), $teamMenuOverride);
    }
}
