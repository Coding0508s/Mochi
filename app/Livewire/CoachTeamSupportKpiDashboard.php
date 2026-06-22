<?php

namespace App\Livewire;

use App\Support\CoachTeamCoachScheduleBuilder;
use App\Support\CoachTeamKpiAggregator;
use App\Support\TeacherSupportKpiCalculator;
use App\Support\TeamMenuContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CoachTeamSupportKpiDashboard extends Component
{
    public int $filterYear;

    public string $filterMonth = '';

    public string $filterRound = '';

    public string $highlightKpi = '';

    public bool $showCoachScheduleModal = false;

    public string $coachScheduleModalCoach = '';

    /** @var list<array<string, mixed>> */
    public array $coachScheduleRows = [];

    /** @var array<string, int> */
    public array $coachScheduleKpis = [];

    /** @var array<string, mixed> */
    public array $coachScheduleSummary = [];

    public function mount(): void
    {
        Gate::authorize('viewCoachTeamKpi');

        $this->filterYear = (int) request()->query(
            'filterYear',
            config('coach_teacher_support.default_year') ?? now()->year,
        );

        $month = request()->query('filterMonth');
        if (is_string($month) && $month !== '' && (int) $month >= 1 && (int) $month <= 12) {
            $this->filterMonth = (string) (int) $month;
        }

        $round = request()->query('filterRound');
        if (is_string($round) && in_array($round, ['1', '2', '3', '4'], true)) {
            $this->filterRound = $round;
        }
    }

    public function updatingFilterYear(): void
    {
        $this->highlightKpi = '';
    }

    public function updatingFilterMonth(): void
    {
        $this->highlightKpi = '';
    }

    public function updatingFilterRound(): void
    {
        $this->highlightKpi = '';
    }

    public function setHighlightKpi(string $kpi): void
    {
        $this->highlightKpi = $this->highlightKpi === $kpi ? '' : $kpi;
    }

    public function resetFilters(): void
    {
        $this->filterMonth = '';
        $this->filterRound = '';
        $this->highlightKpi = '';
    }

    public function clearMonthFilter(): void
    {
        $this->filterMonth = '';
    }

    public function clearRoundFilter(): void
    {
        $this->filterRound = '';
    }

    public function teacherSupportUrl(string $coach): string
    {
        return TeamMenuContext::route('coach.teacher-support.index', array_filter([
            'filterYear' => $this->filterYear,
            'filterMonth' => $this->filterMonth !== '' ? $this->filterMonth : null,
            'filterRound' => $this->filterRound !== '' ? $this->filterRound : null,
            'filterCoach' => $coach,
        ]));
    }

    public function openCoachScheduleModal(string $coach): void
    {
        Gate::authorize('viewCoachTeamKpi');

        $coach = trim($coach);
        if ($coach === '') {
            return;
        }

        $query = CoachTeamKpiAggregator::teamBaseQuery();
        CoachTeamKpiAggregator::applyCoachTrFilter($query, $coach);
        CoachTeamKpiAggregator::applyScheduleFilters(
            $query,
            $this->filterYear,
            $this->filterMonth,
            $this->filterRound,
        );

        $schedules = CoachTeamCoachScheduleBuilder::fromQuery($query, $this->filterYear);
        $this->coachScheduleRows = $schedules->all();
        $coachTeachers = (clone $query)
            ->select('Teachers.*')
            ->get();
        $this->coachScheduleKpis = TeacherSupportKpiCalculator::calculateFromTeachers($coachTeachers, $this->filterYear);
        $this->coachScheduleSummary = CoachTeamCoachScheduleBuilder::summaryFromSchedules($schedules);
        $this->coachScheduleModalCoach = $coach;
        $this->showCoachScheduleModal = true;
    }

    public function closeCoachScheduleModal(): void
    {
        $this->showCoachScheduleModal = false;
        $this->coachScheduleModalCoach = '';
        $this->coachScheduleRows = [];
        $this->coachScheduleKpis = [];
        $this->coachScheduleSummary = [];
    }

    public function render()
    {
        Gate::authorize('viewCoachTeamKpi');

        $baseQuery = CoachTeamKpiAggregator::teamBaseQuery();

        $teamKpis = CoachTeamKpiAggregator::teamTotals(
            $baseQuery,
            $this->filterYear,
            $this->filterMonth,
            $this->filterRound,
        );

        $coachRows = CoachTeamKpiAggregator::byCoach(
            $baseQuery,
            $this->filterYear,
            $this->filterMonth,
            $this->filterRound,
        );

        if ($this->highlightKpi !== '') {
            $coachRows = $this->sortCoachRowsByKpi($coachRows, $this->highlightKpi);
        }

        return view('livewire.coach-team-support-kpi-dashboard', [
            'teamKpis' => $teamKpis,
            'coachRows' => $coachRows,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortCoachRowsByKpi(Collection $rows, string $kpiKey): Collection
    {
        if (! in_array($kpiKey, TeacherSupportKpiCalculator::sortableKpiKeys(), true)) {
            return $rows;
        }

        return $rows
            ->sortByDesc(fn (array $row): int => $row[$kpiKey] ?? 0)
            ->values();
    }
}
