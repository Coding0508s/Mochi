<?php

namespace App\Support;

use App\Models\AccountInformation;
use App\Models\Institution;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Coach 팀 KPI — 담당 Coach(TR)별 지원 완료 차수 집계.
 *
 * @phpstan-type CoachKpiRow array{
 *     coach: string,
 *     teacher_count: int,
 *     first_round: int,
 *     second_round: int,
 *     third_round: int,
 *     fourth_round: int,
 *     completed: int,
 *     unsupported: int,
 * }
 */
final class CoachTeamKpiAggregator
{
    /**
     * 팀 KPI용 교사 베이스 쿼리 (퇴직 제외·숨김 기관 제외·TR 배정 기관만).
     *
     * @return Builder<Teacher>
     */
    public static function teamBaseQuery(): Builder
    {
        $query = Teacher::query();
        $query->excludeRetired();
        CoachTeacherScope::excludeHiddenInstitutions($query);
        $query->whereHas('institution.accountInfo', function (Builder $sub): void {
            $sub->whereNotNull('TR')->where('TR', '!=', '');
        });

        return $query;
    }

    /**
     * @return array<string, int>
     */
    public static function teamTotals(Builder $baseQuery, int $year, string $filterMonth = '', string $filterRound = ''): array
    {
        $query = clone $baseQuery;
        self::applyScheduleFilters($query, $year, $filterMonth, $filterRound);

        return TeacherSupportKpiCalculator::calculateAggregated($query, $year);
    }

    /**
     * @return Collection<int, CoachKpiRow>
     */
    public static function byCoach(Builder $baseQuery, int $year, string $filterMonth = '', string $filterRound = ''): Collection
    {
        $query = clone $baseQuery;
        self::applyScheduleFilters($query, $year, $filterMonth, $filterRound);

        $teacherTable = (new Teacher)->getTable();
        $institutionTable = (new Institution)->getTable();
        $accountTable = (new AccountInformation)->getTable();
        $sqlNormalizedTr = ManagerNameNormalizer::sqlColumnExpression('team_kpi_ai.TR');
        $teacherIdColumn = "{$teacherTable}.ID";

        $accountBySkSubquery = DB::table($accountTable)
            ->selectRaw('SK_Code, MAX(TR) as TR')
            ->whereNotNull('TR')
            ->where('TR', '!=', '')
            ->groupBy('SK_Code');

        $query
            ->join($institutionTable.' as team_kpi_inst', "{$teacherTable}.SK_Code", '=', 'team_kpi_inst.SKcode')
            ->joinSub($accountBySkSubquery, 'team_kpi_ai', 'team_kpi_inst.SKcode', '=', 'team_kpi_ai.SK_Code');

        $aggregates = TeacherSupportKpiCalculator::aggregateSelectExpressions(
            $teacherIdColumn,
            "{$teacherTable}.",
            $year,
        );

        $selects = [
            DB::raw("{$sqlNormalizedTr} as normalized_tr"),
            DB::raw('MIN(team_kpi_ai.TR) as coach'),
            ...array_values($aggregates),
        ];

        return $query
            ->select($selects)
            ->groupBy(DB::raw($sqlNormalizedTr))
            ->orderBy('coach')
            ->toBase()
            ->get()
            ->map(static function ($row): array {
                $mapped = [
                    'coach' => (string) $row->coach,
                    'teacher_count' => (int) $row->teacher_count,
                ];

                foreach (TeacherSupportKpiCalculator::roundKpiKeys() as $key) {
                    $mapped[$key] = (int) ($row->{$key} ?? 0);
                }

                $mapped['completed'] = (int) ($row->completed ?? 0);
                $mapped['unsupported'] = (int) ($row->unsupported ?? 0);

                return $mapped;
            })
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function distinctCoachTrNames(Builder $baseQuery): Collection
    {
        $skCodes = (clone $baseQuery)
            ->select('Teachers.SK_Code')
            ->distinct()
            ->pluck('SK_Code')
            ->filter(fn ($value): bool => filled($value))
            ->values();

        if ($skCodes->isEmpty()) {
            return collect();
        }

        return AccountInformation::query()
            ->whereIn('SK_Code', $skCodes->all())
            ->whereNotNull('TR')
            ->where('TR', '!=', '')
            ->distinct()
            ->orderBy('TR')
            ->pluck('TR');
    }

    /**
     * @param  Builder<Teacher>  $query
     */
    public static function applyCoachTrFilter(Builder $query, string $coach): void
    {
        $normalizedCoach = ManagerNameNormalizer::normalize($coach);
        $sqlNormalizedTr = ManagerNameNormalizer::sqlColumnExpression('TR');

        $query->whereHas('institution.accountInfo', function (Builder $sub) use ($normalizedCoach, $sqlNormalizedTr): void {
            $sub->whereRaw("{$sqlNormalizedTr} = ?", [$normalizedCoach]);
        });
    }

    /**
     * @param  Builder<Teacher>  $query
     */
    public static function applyScheduleFilters(Builder $query, int $year, string $filterMonth, string $filterRound): void
    {
        if ($filterRound !== '') {
            TeacherSupportKpiCalculator::applyPlanRoundScope($query, $filterRound, $year);
        }

        if ($filterMonth === '') {
            if ($filterRound === '') {
                self::applyAnySupportYearScope($query, $year);
            }

            return;
        }

        $month = (int) $filterMonth;
        $planRound = $filterRound !== '' ? $filterRound : '1';
        $planColumn = TeacherSupportKpiCalculator::planColumnForFilterRound($planRound);

        if ($planColumn === null) {
            return;
        }

        $query->whereNotNull($planColumn)
            ->where(function (Builder $nested) use ($planColumn, $year, $month): void {
                ExcelSerialDate::applyWhereYear($nested, $planColumn, $year);
                $nested->whereMonth($planColumn, $month);
            });
    }

    /**
     * @param  Builder<Teacher>  $query
     */
    public static function applyAnyPlanYearScope(Builder $query, int $year): void
    {
        self::applyAnySupportYearScope($query, $year, planOnly: true);
    }

    /**
     * 선택 연도에 계획 또는 완료가 하나라도 있는 교사(차수 기준).
     *
     * @param  Builder<Teacher>  $query
     */
    public static function applyAnySupportYearScope(Builder $query, int $year, bool $planOnly = false): void
    {
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);

        $query->where(function (Builder $outer) use ($cols, $rounds, $year, $planOnly): void {
            $first = true;
            foreach ($rounds as $round) {
                $planCol = $cols[$round['plan']] ?? null;
                $completedCol = $cols[$round['completed']] ?? null;

                if ($planCol === null) {
                    continue;
                }

                $clause = function (Builder $sub) use ($planCol, $completedCol, $year, $planOnly): void {
                    $sub->where(function (Builder $inner) use ($planCol, $completedCol, $year, $planOnly): void {
                        $inner->where(function (Builder $plan) use ($planCol, $year): void {
                            $plan->whereNotNull($planCol);
                            ExcelSerialDate::applyWhereYear($plan, $planCol, $year);
                        });

                        if (! $planOnly && $completedCol !== null) {
                            $inner->orWhere(function (Builder $completed) use ($completedCol, $year): void {
                                $completed->whereNotNull($completedCol);
                                ExcelSerialDate::applyWhereYear($completed, $completedCol, $year);
                            });
                        }
                    });
                };

                if ($first) {
                    $outer->where($clause);
                    $first = false;
                } else {
                    $outer->orWhere($clause);
                }
            }
        });
    }
}
