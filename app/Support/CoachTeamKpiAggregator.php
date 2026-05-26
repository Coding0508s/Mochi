<?php

namespace App\Support;

use App\Models\AccountInformation;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

        return TeacherSupportKpiCalculator::calculate($query, $year);
    }

    /**
     * @return Collection<int, CoachKpiRow>
     */
    public static function byCoach(Builder $baseQuery, int $year, string $filterMonth = '', string $filterRound = ''): Collection
    {
        return self::distinctCoachTrNames($baseQuery)
            ->map(function (string $coach) use ($baseQuery, $year, $filterMonth, $filterRound): array {
                $query = clone $baseQuery;
                self::applyCoachTrFilter($query, $coach);
                self::applyScheduleFilters($query, $year, $filterMonth, $filterRound);

                $kpis = TeacherSupportKpiCalculator::calculate($query, $year);

                return array_merge([
                    'coach' => $coach,
                    'teacher_count' => (clone $query)->count(),
                ], $kpis);
            });
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
            return;
        }

        $month = (int) $filterMonth;
        $planRound = $filterRound !== '' ? $filterRound : '1';
        $planColumn = TeacherSupportKpiCalculator::planColumnForFilterRound($planRound);

        if ($planColumn === null) {
            return;
        }

        $query->whereNotNull($planColumn)
            ->whereYear($planColumn, $year)
            ->whereMonth($planColumn, $month);
    }
}
