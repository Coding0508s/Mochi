<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;

/**
 * Coach 교사 지원 현황 KPI 4종 계산기.
 *
 * 동일한 scoped Builder 위에서 집계하므로 목록과 KPI 수치가 항상 일치합니다.
 *
 * @phpstan-type KpiResult array{first_round: int, second_round: int, completed: int, unsupported: int}
 */
final class TeacherSupportKpiCalculator
{
    /**
     * @param  Builder<Teacher>  $baseQuery  CoachTeacherScope 적용된 쿼리
     * @return KpiResult
     */
    public static function calculate(Builder $baseQuery, int $year): array
    {
        $cols = config('coach_teacher_support.columns');

        $col1st = $cols['completed_1st'];
        $col2nd = $cols['completed_2nd'];
        $colPlan1st = $cols['plan_1st'];
        $colPlan2nd = $cols['plan_2nd'];

        $firstRound = (clone $baseQuery)
            ->whereNotNull($col1st)
            ->whereYear($col1st, $year)
            ->count();

        $secondRound = (clone $baseQuery)
            ->whereNotNull($col2nd)
            ->whereYear($col2nd, $year)
            ->count();

        $completed = (clone $baseQuery)
            ->whereNotNull($col1st)
            ->whereYear($col1st, $year)
            ->whereNotNull($col2nd)
            ->whereYear($col2nd, $year)
            ->count();

        $unsupported = (clone $baseQuery)
            ->where(function (Builder $q) use ($colPlan1st, $colPlan2nd, $col1st, $col2nd, $year): void {
                $q->where(function (Builder $sub) use ($colPlan1st, $col1st, $year): void {
                    $sub->whereNotNull($colPlan1st)
                        ->where(function (Builder $inner) use ($col1st, $year): void {
                            $inner->whereNull($col1st)
                                ->orWhereYear($col1st, '!=', $year);
                        });
                })->orWhere(function (Builder $sub) use ($colPlan2nd, $col2nd, $year): void {
                    $sub->whereNotNull($colPlan2nd)
                        ->where(function (Builder $inner) use ($col2nd, $year): void {
                            $inner->whereNull($col2nd)
                                ->orWhereYear($col2nd, '!=', $year);
                        });
                });
            })
            ->count();

        return [
            'first_round' => $firstRound,
            'second_round' => $secondRound,
            'completed' => $completed,
            'unsupported' => $unsupported,
        ];
    }
}
