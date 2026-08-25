<?php

namespace App\Livewire;

use App\Support\CoachTeamSupportMatrixAggregator;
use App\Support\ManagerNameNormalizer;
use App\Support\TeacherSupportHistoryDetailResolver;
use App\Support\TeamMenuContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoachTeamSupportKpiDashboard extends Component
{
    /** 연도 문자열. 빈 문자열이면 최근 4년(전체). */
    public string $filterYear = '';

    public string $searchCoach = '';

    public bool $showListModal = false;

    public string $listModalCoach = '';

    public string $listModalRowKey = '';

    public ?string $listModalPeriodKey = null;

    /** @var list<array<string, mixed>> */
    public array $listModalItems = [];

    public bool $showDetailModal = false;

    /** @var array{title: string, subtitle: string, sections: list<array{title: string, fields: list<array{label: string, value: string}>}>}|null */
    public ?array $selectedDetail = null;

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
    }

    public function updatingFilterYear(): void
    {
        $this->closeListModal();
    }

    public function updatingSearchCoach(): void
    {
        // no-op hook for live search
    }

    public function resolvedYear(): ?int
    {
        return $this->filterYear === '' ? null : (int) $this->filterYear;
    }

    public function yearLabel(): string
    {
        return CoachTeamSupportMatrixAggregator::yearRangeLabel($this->resolvedYear());
    }

    public function teacherSupportUrl(string $coach): string
    {
        $year = $this->resolvedYear() ?? now()->year;

        return TeamMenuContext::route('coach.teacher-support.index', array_filter([
            'filterYear' => $year,
            'filterCoach' => $coach,
        ]));
    }

    public function exportToExcel(): ?StreamedResponse
    {
        Gate::authorize('viewCoachTeamKpi');

        try {
            $items = CoachTeamSupportMatrixAggregator::exportDetailItems(
                $this->resolvedYear(),
                $this->searchCoach,
            );

            if ($items === []) {
                session()->flash('error', '다운로드할 데이터가 없습니다.');

                return null;
            }

            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('지원 내역');

            $headers = [
                '담당 Coach',
                '구분',
                '유형(행)',
                '유형(상세)',
                '지원일',
                '월',
                '대상',
                '기관',
                '상태',
            ];

            foreach ($headers as $index => $header) {
                $column = chr(65 + $index);
                $sheet->setCellValue($column.'1', $header);
                $sheet->getStyle($column.'1')->getFont()->setBold(true);
            }

            $rowNumber = 2;
            foreach ($items as $item) {
                $sheet->fromArray([
                    $item['coach'],
                    $item['group_label'],
                    $item['row_label'],
                    $item['type_label'],
                    $item['date'],
                    $item['month'],
                    $item['subject'],
                    $item['institution'] !== '' ? $item['institution'] : '—',
                    $item['status'] !== '' ? $item['status'] : '—',
                ], null, 'A'.$rowNumber);
                $rowNumber++;
            }

            foreach (range('A', 'I') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $yearPart = $this->filterYear === ''
                ? '전체_'.str_replace('~', '-', str_replace('년', '', $this->yearLabel()))
                : $this->filterYear;

            $fileName = 'Coach_Team_지원내역_'.$yearPart.'_'.now()->format('Ymd_His').'.xlsx';

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

    public function openCellModal(string $coach, string $rowKey, string|int|null $periodKey = null): void
    {
        Gate::authorize('viewCoachTeamKpi');

        $coach = trim($coach);
        $rowKey = trim($rowKey);
        if ($coach === '' || $rowKey === '') {
            return;
        }

        $resolvedPeriodKey = null;
        if ($periodKey !== null && $periodKey !== '') {
            $normalized = (string) $periodKey;
            $validKeys = array_map(
                static fn (array $column): string => (string) $column['key'],
                CoachTeamSupportMatrixAggregator::periodColumns($this->resolvedYear()),
            );
            if (in_array($normalized, $validKeys, true)) {
                $resolvedPeriodKey = $normalized;
            }
        }

        $this->listModalCoach = $coach;
        $this->listModalRowKey = $rowKey;
        $this->listModalPeriodKey = $resolvedPeriodKey;
        $this->listModalItems = CoachTeamSupportMatrixAggregator::detailItems(
            $this->resolvedYear(),
            $coach,
            $rowKey,
            $resolvedPeriodKey,
        );
        $this->showListModal = true;
        $this->closeTeacherSupportHistoryDetailModal();
    }

    public function closeListModal(): void
    {
        $this->showListModal = false;
        $this->listModalCoach = '';
        $this->listModalRowKey = '';
        $this->listModalPeriodKey = null;
        $this->listModalItems = [];
        $this->closeTeacherSupportHistoryDetailModal();
    }

    public function openDetailFromList(string $detailKey): void
    {
        Gate::authorize('viewCoachTeamKpi');

        if ($detailKey === '') {
            return;
        }

        $detail = app(TeacherSupportHistoryDetailResolver::class)->resolve($detailKey);
        if ($detail === null) {
            return;
        }

        $this->selectedDetail = $detail;
        $this->showDetailModal = true;
    }

    public function closeTeacherSupportHistoryDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedDetail = null;
    }

    public function render()
    {
        Gate::authorize('viewCoachTeamKpi');

        $allCoachRows = CoachTeamSupportMatrixAggregator::byCoach($this->resolvedYear());
        $teamTotal = (int) $allCoachRows->sum('total');

        $search = trim($this->searchCoach);
        $coachRows = $allCoachRows;
        if ($search !== '') {
            $needle = ManagerNameNormalizer::normalize($search);
            $coachRows = $allCoachRows
                ->filter(fn (array $row): bool => str_contains(
                    ManagerNameNormalizer::normalize($row['coach']),
                    $needle,
                ))
                ->values();
        }

        $matrixRows = CoachTeamSupportMatrixAggregator::matrixRowDefinitions();
        $periodColumns = CoachTeamSupportMatrixAggregator::periodColumns($this->resolvedYear());
        $rowLabels = collect($matrixRows)->pluck('label', 'key')->all();

        $activeCoachRows = $coachRows
            ->filter(fn (array $row): bool => (int) $row['total'] > 0)
            ->values();
        $zeroCoachRows = $coachRows
            ->filter(fn (array $row): bool => (int) $row['total'] === 0)
            ->values();

        $listModalPeriodLabel = CoachTeamSupportMatrixAggregator::periodLabel(
            $this->resolvedYear(),
            $this->listModalPeriodKey,
        );

        return view('livewire.coach-team-support-kpi-dashboard', [
            'coachRows' => $coachRows,
            'activeCoachRows' => $activeCoachRows,
            'zeroCoachRows' => $zeroCoachRows,
            'matrixRows' => $matrixRows,
            'periodColumns' => $periodColumns,
            'teamTotal' => $teamTotal,
            'filteredCoachCount' => $coachRows->count(),
            'rowLabels' => $rowLabels,
            'yearLabel' => $this->yearLabel(),
            'listModalRowLabel' => $rowLabels[$this->listModalRowKey] ?? $this->listModalRowKey,
            'listModalPeriodLabel' => $listModalPeriodLabel,
        ]);
    }
}
