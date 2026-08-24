<?php

namespace App\Livewire;

use App\Support\CoachTeacherScope;
use App\Support\CoachTeamInstitutionCoverageAggregator;
use App\Support\CoachTeamSupportMatrixAggregator;
use App\Support\ManagerNameNormalizer;
use App\Support\TeacherSupportHistoryDetailResolver;
use App\Support\TeamMenuContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoachInstitutionCoverageList extends Component
{
    use WithPagination;

    /** 연도 문자열. 빈 문자열이면 KPI와 동일하게 최근 연도(전체). */
    public string $filterYear = '';

    public string $search = '';

    public string $filterCoach = '';

    public string $coverageFilter = '';

    public bool $showTypeDetailModal = false;

    public string $typeDetailSkCode = '';

    public string $typeDetailTypeKey = '';

    public string $typeDetailInstitution = '';

    public string $typeDetailTypeLabel = '';

    /** @var list<array{id: int|null, date: string, coach: string, type: string, status: string, detail_key: string}> */
    public array $typeDetailRows = [];

    public bool $showTeacherSupportHistoryDetailModal = false;

    /** @var array{title: string, subtitle: string, sections: list<array{title: string, fields: list<array{label: string, value: string}>}>}|null */
    public ?array $selectedTeacherSupportHistoryDetail = null;

    public function mount(): void
    {
        Gate::authorize('viewCoachTeamKpi');

        $queryYear = request()->query('filterYear');
        if ($queryYear === '' || $queryYear === 'all') {
            $this->filterYear = '';
        } elseif (is_numeric($queryYear)) {
            $this->filterYear = (string) (int) $queryYear;
        } else {
            $this->filterYear = (string) (config('coach_teacher_support.default_year') ?? now()->year);
        }

        $coach = request()->query('filterCoach');
        if (is_string($coach) && filled($coach)) {
            $this->filterCoach = $coach;
        }

        $coverage = request()->query('coverageFilter');
        if (is_string($coverage) && in_array($coverage, CoachTeamInstitutionCoverageAggregator::coverageFilterKeys(), true)) {
            $this->coverageFilter = $coverage;
        }

        $this->filterCoach = $this->resolveAllowedFilterCoach();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterYear(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCoach(): void
    {
        $this->resetPage();
    }

    public function updatingCoverageFilter(): void
    {
        $this->resetPage();
    }

    public function setCoverageFilter(string $key): void
    {
        if ($key !== '' && ! in_array($key, CoachTeamInstitutionCoverageAggregator::coverageFilterKeys(), true)) {
            return;
        }

        $this->coverageFilter = $this->coverageFilter === $key ? '' : $key;
        $this->resetPage();
    }

    public function clearCoverageFilter(): void
    {
        $this->coverageFilter = '';
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function clearCoachFilter(): void
    {
        $this->filterCoach = '';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterCoach = '';
        $this->coverageFilter = '';
        $this->resetPage();
    }

    public function openTypeDetail(string $skCode, string $typeKey): void
    {
        Gate::authorize('viewCoachTeamKpi');

        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $detail = CoachTeamInstitutionCoverageAggregator::typeDetail(
            $skCode,
            $typeKey,
            $this->resolvedYear(),
            $user,
        );

        if ($detail === null) {
            return;
        }

        $this->typeDetailSkCode = $detail['sk_code'];
        $this->typeDetailTypeKey = $detail['type_key'];
        $this->typeDetailTypeLabel = $detail['type_label'];
        $this->typeDetailInstitution = $detail['institution'];
        $this->typeDetailRows = $detail['rows'];
        $this->showTypeDetailModal = true;
    }

    public function closeTypeDetail(): void
    {
        $this->closeTeacherSupportHistoryDetailModal();
        $this->showTypeDetailModal = false;
        $this->typeDetailSkCode = '';
        $this->typeDetailTypeKey = '';
        $this->typeDetailInstitution = '';
        $this->typeDetailTypeLabel = '';
        $this->typeDetailRows = [];
    }

    public function openTypeDetailRecord(string $detailKey): void
    {
        Gate::authorize('viewCoachTeamKpi');

        if ($detailKey === '' || $this->typeDetailSkCode === '') {
            return;
        }

        $allowed = false;
        foreach ($this->typeDetailRows as $row) {
            if (($row['detail_key'] ?? '') === $detailKey) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            return;
        }

        $detail = app(TeacherSupportHistoryDetailResolver::class)->resolve(
            $detailKey,
            null,
            $this->typeDetailSkCode,
        );

        if ($detail === null) {
            return;
        }

        $this->selectedTeacherSupportHistoryDetail = $detail;
        $this->showTeacherSupportHistoryDetailModal = true;
    }

    public function closeTeacherSupportHistoryDetailModal(): void
    {
        $this->showTeacherSupportHistoryDetailModal = false;
        $this->selectedTeacherSupportHistoryDetail = null;
    }

    public function resolvedYear(): ?int
    {
        return $this->filterYear === '' ? null : (int) $this->filterYear;
    }

    public function yearLabel(): string
    {
        return CoachTeamSupportMatrixAggregator::yearRangeLabel($this->resolvedYear());
    }

    public function exportToExcel(): ?StreamedResponse
    {
        Gate::authorize('viewCoachTeamKpi');

        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        try {
            $rows = CoachTeamInstitutionCoverageAggregator::rows(
                $this->resolvedYear(),
                $user,
                $this->search,
                $this->resolveAllowedFilterCoach(),
                $this->coverageFilter,
            );

            if ($rows->isEmpty()) {
                session()->flash('error', '다운로드할 데이터가 없습니다.');

                return null;
            }

            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('기관 지원 현황');

            $headers = [
                'No',
                'SK코드',
                '기관명',
                'Coach',
                '대면',
                '전화',
                '화상',
                '합계',
            ];

            foreach ($headers as $index => $header) {
                $column = chr(65 + $index);
                $sheet->setCellValue($column.'1', $header);
                $sheet->getStyle($column.'1')->getFont()->setBold(true);
            }

            $rowNumber = 2;
            foreach ($rows->values() as $index => $row) {
                $sheet->fromArray([
                    $index + 1,
                    $row['sk_code'],
                    $row['institution'],
                    $row['coach'] !== '' ? $row['coach'] : '-',
                    CoachTeamInstitutionCoverageAggregator::formatCount((int) $row['visit_count']),
                    CoachTeamInstitutionCoverageAggregator::formatCount((int) $row['phone_count']),
                    CoachTeamInstitutionCoverageAggregator::formatCount((int) $row['video_count']),
                    CoachTeamInstitutionCoverageAggregator::formatCount((int) $row['institution_total_count']),
                ], null, 'A'.$rowNumber);
                $rowNumber++;
            }

            foreach (range('A', 'H') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $yearPart = $this->filterYear === ''
                ? '전체_'.str_replace('~', '-', str_replace('년', '', $this->yearLabel()))
                : $this->filterYear;

            $fileName = 'Coach_Team_기관지원현황_'.$yearPart.'_'.now()->format('Ymd_His').'.xlsx';

            return response()->streamDownload(function () use ($spreadsheet): void {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception) {
            session()->flash('error', '엑셀 다운로드 중 오류가 발생했습니다.');

            return null;
        }
    }

    public function render()
    {
        Gate::authorize('viewCoachTeamKpi');

        $user = Auth::user();
        if ($user === null) {
            abort(403);
        }

        $allowedCoach = $this->resolveAllowedFilterCoach();
        $year = $this->resolvedYear();

        $counts = CoachTeamInstitutionCoverageAggregator::counts(
            $year,
            $user,
            $this->search,
            $allowedCoach,
        );

        $rows = CoachTeamInstitutionCoverageAggregator::rows(
            $year,
            $user,
            $this->search,
            $allowedCoach,
            $this->coverageFilter,
        );

        $page = max(1, (int) $this->getPage());
        $perPage = 50;
        $total = $rows->count();
        $items = $rows->forPage($page, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );

        return view('livewire.coach-institution-coverage-list', [
            'institutions' => $paginator,
            'counts' => $counts,
            'coverageFilterLabels' => CoachTeamInstitutionCoverageAggregator::coverageFilterLabels(),
            'coachFilterOptions' => $this->coachFilterOptions(),
            'yearLabel' => $this->yearLabel(),
            'yearFilterOptions' => $this->yearFilterOptions(),
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function coachFilterOptions(): Collection
    {
        $user = Auth::user();
        if ($user === null) {
            return collect();
        }

        [$displayByPrimary, $aliasToPrimary] = CoachTeamSupportMatrixAggregator::coachTeamMemberMaps();
        if ($displayByPrimary === []) {
            return collect();
        }

        if ($user->canViewCoachTeamKpi()
            || TeamMenuContext::hasExpandedReadScope($user)
            || TeamMenuContext::hasAdminMenuDataScope($user)) {
            return collect(array_values($displayByPrimary))->sort()->values();
        }

        $names = [];
        foreach (CoachTeacherScope::resolveTrAliases($user) as $alias) {
            $primary = $aliasToPrimary[$alias] ?? null;
            if ($primary !== null && isset($displayByPrimary[$primary])) {
                $names[$primary] = $displayByPrimary[$primary];
            }
        }

        return collect(array_values($names))->sort()->values();
    }

    private function resolveAllowedFilterCoach(): string
    {
        if (! filled($this->filterCoach)) {
            return '';
        }

        $user = Auth::user();
        if ($user === null) {
            return '';
        }

        if ($user->canViewCoachTeamKpi()
            || TeamMenuContext::hasExpandedReadScope($user)
            || TeamMenuContext::hasAdminMenuDataScope($user)) {
            return $this->filterCoach;
        }

        $normalizedFilter = ManagerNameNormalizer::normalize($this->filterCoach);
        foreach (CoachTeacherScope::resolveTrAliases($user) as $alias) {
            if ($alias === $normalizedFilter) {
                return $this->filterCoach;
            }
        }

        [, $aliasToPrimary] = CoachTeamSupportMatrixAggregator::coachTeamMemberMaps();
        $filterPrimary = $aliasToPrimary[$normalizedFilter] ?? null;
        if ($filterPrimary === null) {
            return '';
        }

        foreach (CoachTeacherScope::resolveTrAliases($user) as $alias) {
            if (($aliasToPrimary[$alias] ?? null) === $filterPrimary) {
                return $this->filterCoach;
            }
        }

        return '';
    }

    /**
     * @return list<int>
     */
    private function yearFilterOptions(): array
    {
        $years = [];
        for ($y = (int) now()->year; $y >= (int) now()->year - 3; $y--) {
            $years[] = $y;
        }

        return $years;
    }
}
