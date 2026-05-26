<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class TeacherSupportKpiCalculator
{
    /**
     * @return array<string, string>
     */
    public static function toggleLabels(): array
    {
        $roundLabels = collect(config('coach_teacher_support.kpi_rounds', []))
            ->mapWithKeys(fn (array $round, string $key): array => [$key => $round['label']])
            ->all();

        return array_merge($roundLabels, config('coach_teacher_support.kpi_aggregate_labels', []));
    }

    /**
     * @return list<string>
     */
    public static function roundKpiKeys(): array
    {
        return array_keys(config('coach_teacher_support.kpi_rounds', []));
    }

    /**
     * @return list<string>
     */
    public static function sortableKpiKeys(): array
    {
        return array_keys(self::toggleLabels());
    }

    /**
     * @return array<string, int>
     */
    public static function calculate(Builder $baseQuery, int $year): array
    {
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);
        $result = [];

        foreach ($rounds as $key => $round) {
            $completedCol = $cols[$round['completed']];
            $result[$key] = (clone $baseQuery)
                ->whereNotNull($completedCol)
                ->whereYear($completedCol, $year)
                ->count();
        }

        $completedQuery = clone $baseQuery;
        foreach ($rounds as $round) {
            $completedCol = $cols[$round['completed']];
            $completedQuery
                ->whereNotNull($completedCol)
                ->whereYear($completedCol, $year);
        }
        $result['completed'] = $completedQuery->count();

        $unsupportedQuery = clone $baseQuery;
        self::applyUnsupportedScope($unsupportedQuery, $year);
        $result['unsupported'] = $unsupportedQuery->count();

        return $result;
    }

    public static function applyRoundCompletedScope(Builder $query, string $kpiKey, int $year): void
    {
        $rounds = config('coach_teacher_support.kpi_rounds', []);
        if (! isset($rounds[$kpiKey])) {
            return;
        }

        $cols = config('coach_teacher_support.columns');
        $completedCol = $cols[$rounds[$kpiKey]['completed']];
        $query->whereNotNull($completedCol)->whereYear($completedCol, $year);
    }

    public static function applyAllRoundsCompletedScope(Builder $query, int $year): void
    {
        $cols = config('coach_teacher_support.columns');
        foreach (config('coach_teacher_support.kpi_rounds', []) as $round) {
            $completedCol = $cols[$round['completed']];
            $query->whereNotNull($completedCol)->whereYear($completedCol, $year);
        }
    }

    public static function applyUnsupportedScope(Builder $query, int $year): void
    {
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);

        $query->where(function (Builder $outer) use ($cols, $rounds, $year): void {
            $first = true;
            foreach ($rounds as $round) {
                $planCol = $cols[$round['plan']];
                $completedCol = $cols[$round['completed']];
                $clause = function (Builder $sub) use ($planCol, $completedCol, $year): void {
                    $sub->whereNotNull($planCol)
                        ->where(function (Builder $inner) use ($completedCol, $year): void {
                            $inner->whereNull($completedCol)
                                ->orWhereYear($completedCol, '!=', $year);
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

    public static function planColumnForFilterRound(string $filterRound): ?string
    {
        $cols = config('coach_teacher_support.columns');
        foreach (config('coach_teacher_support.kpi_rounds', []) as $round) {
            if (($round['filter_round'] ?? '') === $filterRound) {
                return $cols[$round['plan']] ?? null;
            }
        }

        return null;
    }

    public static function applyPlanRoundScope(Builder $query, string $filterRound, int $year): void
    {
        $planColumn = self::planColumnForFilterRound($filterRound);
        if ($planColumn === null) {
            return;
        }

        $query->whereNotNull($planColumn)->whereYear($planColumn, $year);
    }
}
