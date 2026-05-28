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
    public static function calculate(Builder $baseQuery, ?int $year): array
    {
        if ($year === null) {
            return self::calculateWithoutYearFilter($baseQuery);
        }

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

    /**
     * @return array<string, int>
     */
    private static function calculateWithoutYearFilter(Builder $baseQuery): array
    {
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);
        $result = [];

        foreach ($rounds as $key => $round) {
            $completedCol = $cols[$round['completed']];
            $result[$key] = (clone $baseQuery)->whereNotNull($completedCol)->count();
        }

        $completedQuery = clone $baseQuery;
        foreach ($rounds as $round) {
            $completedCol = $cols[$round['completed']];
            $completedQuery->whereNotNull($completedCol);
        }
        $result['completed'] = $completedQuery->count();

        $unsupportedQuery = clone $baseQuery;
        self::applyUnsupportedWithoutYearScope($unsupportedQuery);
        $result['unsupported'] = $unsupportedQuery->count();

        return $result;
    }

    public static function applyKpiFilterWithoutYear(Builder $query, string $kpiKey): void
    {
        match ($kpiKey) {
            'completed' => self::applyAllRoundsCompletedWithoutYearScope($query),
            'unsupported' => self::applyUnsupportedWithoutYearScope($query),
            default => self::applyRoundCompletedWithoutYearScope($query, $kpiKey),
        };
    }

    public static function applyRoundCompletedWithoutYearScope(Builder $query, string $kpiKey): void
    {
        $rounds = config('coach_teacher_support.kpi_rounds', []);
        if (! isset($rounds[$kpiKey])) {
            return;
        }

        $cols = config('coach_teacher_support.columns');
        $completedCol = $cols[$rounds[$kpiKey]['completed']];
        $query->whereNotNull($completedCol);
    }

    public static function applyAllRoundsCompletedWithoutYearScope(Builder $query): void
    {
        $cols = config('coach_teacher_support.columns');
        foreach (config('coach_teacher_support.kpi_rounds', []) as $round) {
            $completedCol = $cols[$round['completed']];
            $query->whereNotNull($completedCol);
        }
    }

    public static function applyUnsupportedWithoutYearScope(Builder $query): void
    {
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);

        $query->where(function (Builder $outer) use ($cols, $rounds): void {
            $first = true;
            foreach ($rounds as $round) {
                $planCol = $cols[$round['plan']];
                $completedCol = $cols[$round['completed']];
                $clause = function (Builder $sub) use ($planCol, $completedCol): void {
                    $sub->whereNotNull($planCol)->whereNull($completedCol);
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

    /**
     * 이미 조회된 교사 컬렉션 기준 KPI 집계.
     *
     * @param  iterable<object|array<string, mixed>>  $teachers
     * @return array<string, int>
     */
    public static function calculateFromTeachers(iterable $teachers, int $year): array
    {
        $rows = collect($teachers)->values();
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);
        $result = [];

        foreach ($rounds as $key => $round) {
            $completedCol = $cols[$round['completed']];
            $result[$key] = $rows->filter(
                fn ($teacher): bool => ExcelSerialDate::isInYear(data_get($teacher, $completedCol), $year)
            )->count();
        }

        $result['completed'] = $rows->filter(function ($teacher) use ($cols, $rounds, $year): bool {
            foreach ($rounds as $round) {
                $completedCol = $cols[$round['completed']];
                if (! ExcelSerialDate::isInYear(data_get($teacher, $completedCol), $year)) {
                    return false;
                }
            }

            return true;
        })->count();

        $result['unsupported'] = $rows->filter(function ($teacher) use ($cols, $rounds, $year): bool {
            foreach ($rounds as $round) {
                $planCol = $cols[$round['plan']];
                $completedCol = $cols[$round['completed']];

                $hasPlanInYear = ExcelSerialDate::isInYear(data_get($teacher, $planCol), $year);
                $completedInYear = ExcelSerialDate::isInYear(data_get($teacher, $completedCol), $year);

                if ($hasPlanInYear && ! $completedInYear) {
                    return true;
                }
            }

            return false;
        })->count();

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

        $query->whereNotNull($planColumn);
        ExcelSerialDate::applyWhereYear($query, $planColumn, $year);
    }
}
