<?php

namespace App\Livewire\Concerns;

use App\Support\InstitutionAccountListQuery;
use App\Support\TeamMenuContext;

trait ResolvesInstitutionFormPermissions
{
    protected const DEPT_CO = 'A02';

    protected const DEPT_TR = 'A05';

    protected const DEPT_CS = 'A03';

    public function canEditInstitutionDetail(): bool
    {
        return $this->canEditInstitutionDetailCore() || $this->canEditAssignedInstitutionDetailFields();
    }

    public function canEditInstitutionDetailCore(): bool
    {
        return (bool) auth()->user()?->hasFullAccess();
    }

    public function canEditInstitutionDetailCo(): bool
    {
        if ($this->isCrossTeamReadOnlyContext()) {
            return false;
        }

        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        return $this->resolveCurrentUserManagerDept() === self::DEPT_CO;
    }

    public function canEditInstitutionDetailTr(): bool
    {
        if ($this->isCrossTeamReadOnlyContext()) {
            return false;
        }

        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        return $this->resolveCurrentUserManagerDept() === self::DEPT_TR;
    }

    public function canEditInstitutionDetailCs(): bool
    {
        if ($this->isCrossTeamReadOnlyContext()) {
            return false;
        }

        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        return $this->resolveCurrentUserManagerDept() === self::DEPT_CS;
    }

    public function canEditInstitutionDetailSkCode(): bool
    {
        return $this->canEditInstitutionDetailCore();
    }

    public function canEditAssignedInstitutionDetailFields(): bool
    {
        if ($this->isCrossTeamReadOnlyContext()) {
            return false;
        }

        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        $skCode = $this->resolveSelectedInstitutionSkCode();
        if ($skCode === null) {
            return false;
        }

        return app(InstitutionAccountListQuery::class)->currentUserCanManageInstitution($skCode);
    }

    private function isCrossTeamReadOnlyContext(): bool
    {
        return TeamMenuContext::isCrossTeamReadOnlyContext(auth()->user());
    }

    private function resolveCurrentUserManagerDept(): ?string
    {
        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        $workdept = $user->employee?->WORKDEPT;
        if (filled($workdept)) {
            $dept = (string) $workdept;
            if (in_array($dept, [self::DEPT_CO, self::DEPT_TR, self::DEPT_CS], true)) {
                return $dept;
            }
        }

        if ($user->isCoTeam()) {
            return self::DEPT_CO;
        }

        if ($user->isCoachTeam()) {
            return self::DEPT_TR;
        }

        if ($user->isCsTeam()) {
            return self::DEPT_CS;
        }

        return null;
    }

    private function resolveSelectedInstitutionSkCode(): ?string
    {
        if (! property_exists($this, 'selectedInstitution')) {
            return null;
        }

        /** @var mixed $selectedInstitution */
        $selectedInstitution = $this->selectedInstitution;
        if (! is_array($selectedInstitution)) {
            return null;
        }

        $skCode = trim((string) ($selectedInstitution['skcode'] ?? ''));

        return $skCode !== '' ? $skCode : null;
    }
}
