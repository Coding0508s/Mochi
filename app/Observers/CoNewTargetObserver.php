<?php

namespace App\Observers;

use App\Models\CoNewTarget;
use App\Services\PotentialInstitutionToInstitutionSync;

class CoNewTargetObserver
{
    public function __construct(
        private PotentialInstitutionToInstitutionSync $sync
    ) {}

    public function saved(CoNewTarget $target): void
    {
        $sk = trim((string) ($target->AccountCode ?? ''));
        if ($sk === '') {
            return;
        }

        if (!$target->wasRecentlyCreated && !$target->wasChanged('AccountCode')) {
            return;
        }

        $this->sync->syncFromCoNewTarget($target);
    }
}
