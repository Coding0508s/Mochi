<?php

namespace App\Livewire\Concerns;

trait ResolvesInstitutionFormPermissions
{
    protected const DEPT_CO = 'A02';

    protected const DEPT_TR = 'A05';

    protected const DEPT_CS = 'A03';

    public function canEditInstitutionDetail(): bool
    {
        return $this->canEditInstitutionDetailCore()
            || $this->canEditInstitutionDetailCo()
            || $this->canEditInstitutionDetailTr()
            || $this->canEditInstitutionDetailCs();
    }

    public function canEditInstitutionDetailCore(): bool
    {
        return (bool) auth()->user()?->hasFullAccess();
    }

    public function canEditInstitutionDetailCo(): bool
    {
        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        return $this->resolveCurrentUserManagerDept() === self::DEPT_CO;
    }

    public function canEditInstitutionDetailTr(): bool
    {
        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        return $this->resolveCurrentUserManagerDept() === self::DEPT_TR;
    }

    public function canEditInstitutionDetailCs(): bool
    {
        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        return $this->resolveCurrentUserManagerDept() === self::DEPT_CS;
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
}
