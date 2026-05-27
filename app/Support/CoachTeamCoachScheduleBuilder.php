<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 팀 KPI — 담당 Coach별 교사 지원 계획·완료 일정 (모달용).
 */
final class CoachTeamCoachScheduleBuilder
{
    /**
     * @param  Builder<Teacher>  $query
     * @return Collection<int, array{
     *     teacher_id: int,
     *     teacher_name: string,
     *     institution_name: string,
     *     rounds: list<array{
     *         round_key: string,
     *         label: string,
     *         plan_date: string,
     *         plan_type: string,
     *         completed_date: string,
     *         completed_type: string,
     *     }>,
     * }>
     */
    public static function fromQuery(Builder $query, ?int $year = null): Collection
    {
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);

        return $query
            ->with('institution')
            ->orderBy('School_Name')
            ->orderBy('Name')
            ->get()
            ->flatMap(function (Teacher $teacher) use ($cols, $rounds, $year): array {
                $roundRows = [];

                foreach ($rounds as $roundKey => $round) {
                    $planKey = $round['plan'];
                    $planColumn = $cols[$planKey];
                    $planParsed = ExcelSerialDate::parse($teacher->getRawOriginal($planColumn));

                    $completedKey = $round['completed'];
                    $completedColumn = $cols[$completedKey];
                    $planTypeKey = str_replace('plan_', 'plan_type_', $planKey);
                    $completedTypeKey = str_replace('completed_', 'type_', $completedKey);

                    $completedParsed = ExcelSerialDate::parse($teacher->getRawOriginal($completedColumn));

                    if ($planParsed === null && $completedParsed === null) {
                        continue;
                    }

                    $planInYear = $planParsed !== null
                        && ($year === null || $planParsed->year === $year);
                    $completedInYear = $completedParsed !== null
                        && ($year === null || $completedParsed->year === $year);

                    if ($year !== null && ! $planInYear && ! $completedInYear) {
                        continue;
                    }

                    $roundRows[] = [
                        'round_key' => $roundKey,
                        'label' => ($round['filter_round'] ?? '').'차',
                        'plan_date' => $planInYear && $planParsed !== null
                            ? $planParsed->format('Y년 n월 j일')
                            : '—',
                        'plan_type' => $planInYear
                            ? trim((string) ($teacher->{$cols[$planTypeKey]} ?? ''))
                            : '',
                        'completed_date' => $completedInYear && $completedParsed !== null
                            ? $completedParsed->format('Y년 n월 j일')
                            : '—',
                        'completed_type' => $completedInYear
                            ? trim((string) ($teacher->{$cols[$completedTypeKey]} ?? ''))
                            : '',
                    ];
                }

                $institutionName = trim($teacher->institution?->resolvedAccountName() ?? '');
                if ($institutionName === '') {
                    $institutionName = trim((string) ($teacher->School_Name ?? ''));
                }

                if ($roundRows === []) {
                    return [];
                }

                return [[
                    'teacher_id' => (int) $teacher->ID,
                    'teacher_name' => trim((string) ($teacher->Name ?? '')),
                    'institution_name' => $institutionName,
                    'rounds' => $roundRows,
                ]];
            })
            ->values();
    }

    /**
     * 모달에 표시된 계획 일정 기준 집계.
     *
     * @param  Collection<int, array{rounds: list<array{round_key: string, completed_date: string}>}>  $schedules
     * @return array{
     *     teacher_count: int,
     *     planned_round_count: int,
     *     completed_count: int,
     *     pending_count: int,
     *     by_round: array<string, array{label: string, planned: int, completed: int, pending: int}>,
     * }
     */
    public static function summaryFromSchedules(Collection $schedules): array
    {
        $rounds = config('coach_teacher_support.kpi_rounds', []);
        $byRound = [];

        foreach ($rounds as $roundKey => $round) {
            $byRound[$roundKey] = [
                'label' => $round['label'] ?? $roundKey,
                'planned' => 0,
                'completed' => 0,
                'pending' => 0,
            ];
        }

        $plannedRoundCount = 0;
        $completedCount = 0;
        $pendingCount = 0;

        foreach ($schedules as $schedule) {
            foreach ($schedule['rounds'] as $round) {
                $roundKey = $round['round_key'] ?? '';
                if (! isset($byRound[$roundKey])) {
                    continue;
                }

                $plannedRoundCount++;
                $byRound[$roundKey]['planned']++;

                if (($round['completed_date'] ?? '—') !== '—') {
                    $completedCount++;
                    $byRound[$roundKey]['completed']++;
                } else {
                    $pendingCount++;
                    $byRound[$roundKey]['pending']++;
                }
            }
        }

        return [
            'teacher_count' => $schedules->count(),
            'planned_round_count' => $plannedRoundCount,
            'completed_count' => $completedCount,
            'pending_count' => $pendingCount,
            'by_round' => $byRound,
        ];
    }
}
