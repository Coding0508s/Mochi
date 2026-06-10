<?php

namespace App\Livewire\Concerns;

use App\Models\Institution;
use App\Models\Teacher;
use Illuminate\Support\Collection;

/**
 * 복직 모달의 "복직 기관 선택" 상태를 관리합니다.
 *
 * 기본값은 기존 기관 유지(reinstateSkCode = '')이며,
 * 검색으로 다른 기관을 선택하면 해당 기관으로 복직 처리됩니다.
 */
trait ManagesReinstateInstitutionSelection
{
    /** 복직 기관 검색어 (기관명 또는 SK 코드) */
    public string $reinstateInstitutionKeyword = '';

    /** 선택한 복직 기관 SK 코드 ('' = 기존 기관 유지) */
    public string $reinstateSkCode = '';

    /** 선택한 복직 기관명 (표시용) */
    public string $reinstateSchoolName = '';

    /** 기존(퇴직 당시) 기관 표시용 */
    public string $reinstateCurrentSkCode = '';

    public string $reinstateCurrentAccountName = '';

    public function updatedReinstateInstitutionKeyword(string $value): void
    {
        if (filled($this->reinstateSkCode)) {
            return;
        }

        $keyword = trim($value);
        if ($keyword === '') {
            return;
        }

        $institution = Institution::query()
            ->with('accountInfo')
            ->whereNotNull('SKcode')
            ->where(function ($q) use ($keyword): void {
                $q->where('AccountName', $keyword)
                    ->orWhere('SKcode', $keyword)
                    ->orWhereHas('accountInfo', function ($info) use ($keyword): void {
                        $info->where('Account_Name', $keyword);
                    });
            })
            ->first();

        if ($institution) {
            $this->applyReinstateInstitution($institution);
        }
    }

    public function selectReinstateInstitution(string $skCode): void
    {
        $trimmed = trim($skCode);
        if ($trimmed === '') {
            return;
        }

        $institution = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $trimmed)
            ->first();

        if (! $institution) {
            return;
        }

        $this->applyReinstateInstitution($institution);
    }

    public function clearReinstateInstitutionSelection(): void
    {
        $this->reinstateSkCode = '';
        $this->reinstateSchoolName = '';
        $this->reinstateInstitutionKeyword = '';
        $this->resetErrorBag('reinstateSkCode');
    }

    protected function prepareReinstateInstitutionState(Teacher $teacher): void
    {
        $this->clearReinstateInstitutionSelection();
        $this->reinstateCurrentSkCode = trim((string) ($teacher->SK_Code ?? ''));
        $this->reinstateCurrentAccountName = $teacher->displayAccountName();
    }

    protected function resetReinstateInstitutionState(): void
    {
        $this->clearReinstateInstitutionSelection();
        $this->reinstateCurrentSkCode = '';
        $this->reinstateCurrentAccountName = '';
    }

    /**
     * 복직 처리에 사용할 SK 코드 (null = 기존 기관 유지)
     */
    protected function resolvedReinstateSkCode(): ?string
    {
        $skCode = trim($this->reinstateSkCode);

        return $skCode !== '' ? $skCode : null;
    }

    /**
     * @return Collection<int, Institution>
     */
    protected function reinstateInstitutionSuggestions(): Collection
    {
        if (! $this->showReinstateModal || filled($this->reinstateSkCode)) {
            return collect();
        }

        $keyword = trim($this->reinstateInstitutionKeyword);
        if ($keyword === '') {
            return collect();
        }

        return Institution::query()
            ->with('accountInfo')
            ->whereNotNull('SKcode')
            ->search($keyword)
            ->orderBy('AccountName')
            ->limit(15)
            ->get(['SKcode', 'AccountName']);
    }

    private function applyReinstateInstitution(Institution $institution): void
    {
        $this->reinstateSkCode = (string) $institution->SKcode;
        $this->reinstateSchoolName = $institution->resolvedAccountName();
        $this->reinstateInstitutionKeyword = '';
        $this->resetErrorBag('reinstateSkCode');
    }
}
