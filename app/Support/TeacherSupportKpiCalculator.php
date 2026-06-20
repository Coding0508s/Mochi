<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
    public static function hiddenToggleKeys(): array
    {
        return ['completed', 'unsupported'];
    }

    /**
     * KPI 토글 UI에 표시할 라벨(전차 완료·미지원 제외).
     *
     * @return array<string, string>
     */
    public static function visibleToggleLabels(): array
    {
        return collect(self::toggleLabels())
            ->except(self::hiddenToggleKeys())
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function roundKpiKeys(): array
    {
        return array_keys(config('coach_teacher_support.kpi_rounds', []));
    }

    /**
     * 1~4차 완료 KPI 합계(총 지원 횟수).
     *
     * @param  array<string, int>  $kpis
     */
    public static function totalSupportCount(array $kpis): int
    {
        return collect(self::roundKpiKeys())
            ->sum(fn (string $key): int => (int) ($kpis[$key] ?? 0));
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

        return self::calculateAggregated($baseQuery, $year);
    }

    /**
     * 연도별 KPI를 단일 SELECT 로 집계한다 (엑셀 serial·ISO 날짜 혼재 대응).
     *
     * @return array<string, int>
     */
    public static function calculateAggregated(Builder $baseQuery, int $year): array
    {
        $teacherTable = (new Teacher)->getTable();
        $teacherIdColumn = "{$teacherTable}.ID";
        $columnPrefix = "{$teacherTable}.";

        $query = clone $baseQuery;
        $selects = array_values(self::aggregateSelectExpressions($teacherIdColumn, $columnPrefix, $year));
        $row = $query->select($selects)->toBase()->first();

        $result = [];
        $result['teacher_count'] = (int) ($row?->teacher_count ?? 0);
        $result['institution_count'] = (int) ($row?->institution_count ?? 0);

        foreach (array_merge(self::roundKpiKeys(), ['completed', 'unsupported']) as $key) {
            $result[$key] = (int) ($row?->{$key} ?? 0);
        }

        return $result;
    }

    /**
     * TR·팀 합계 GROUP BY 집계용 SELECT 식.
     *
     * @return array<string, Expression>
     */
    public static function aggregateSelectExpressions(string $teacherIdColumn, string $columnPrefix, int $year): array
    {
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);
        $expressions = [
            'teacher_count' => DB::raw(
                self::sqlCountDistinctTeachersWhen($teacherIdColumn, '1 = 1').' as teacher_count'
            ),
            'institution_count' => DB::raw(
                self::sqlCountDistinctInstitutionsWhen($columnPrefix.'SK_Code', '1 = 1').' as institution_count'
            ),
        ];

        foreach ($rounds as $key => $round) {
            $completedCol = $columnPrefix.$cols[$round['completed']];
            $expressions[$key] = DB::raw(
                self::sqlCountDistinctTeachersWhen(
                    $teacherIdColumn,
                    self::sqlColumnCompletedInYear($completedCol, $year),
                ).' as '.$key
            );
        }

        $completedColumns = array_map(
            fn (array $round): string => $columnPrefix.$cols[$round['completed']],
            $rounds,
        );
        $expressions['completed'] = DB::raw(
            self::sqlCountDistinctTeachersWhen(
                $teacherIdColumn,
                self::sqlAllRoundsCompletedInYear($completedColumns, $year),
            ).' as completed'
        );

        $expressions['unsupported'] = DB::raw(
            self::sqlCountDistinctTeachersWhen(
                $teacherIdColumn,
                self::sqlAnyRoundUnsupportedInYear($columnPrefix, $year),
            ).' as unsupported'
        );

        return $expressions;
    }

    private static function sqlColumnCompletedInYear(string $qualifiedColumn, int $year): string
    {
        return ExcelSerialDate::sqlColumnInYear($qualifiedColumn, $year);
    }

    /**
     * @param  list<string>  $qualifiedCompletedColumns
     */
    private static function sqlAllRoundsCompletedInYear(array $qualifiedCompletedColumns, int $year): string
    {
        if ($qualifiedCompletedColumns === []) {
            return '0 = 1';
        }

        $parts = array_map(
            fn (string $column): string => self::sqlColumnCompletedInYear($column, $year),
            $qualifiedCompletedColumns,
        );

        return '('.implode(' AND ', $parts).')';
    }

    private static function sqlAnyRoundUnsupportedInYear(string $columnPrefix, int $year): string
    {
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);
        $clauses = [];

        foreach ($rounds as $round) {
            $planCol = $columnPrefix.$cols[$round['plan']];
            $completedCol = $columnPrefix.$cols[$round['completed']];
            $planInYear = ExcelSerialDate::sqlColumnInYear($planCol, $year);
            $completedInYear = ExcelSerialDate::sqlColumnInYear($completedCol, $year);
            $clauses[] = "({$planInYear} AND ({$completedCol} IS NULL OR NOT ({$completedInYear})))";
        }

        if ($clauses === []) {
            return '0 = 1';
        }

        return '('.implode(' OR ', $clauses).')';
    }

    private static function sqlCountDistinctTeachersWhen(string $teacherIdColumn, string $conditionSql): string
    {
        return "COUNT(DISTINCT CASE WHEN {$conditionSql} THEN {$teacherIdColumn} END)";
    }

    private static function sqlCountDistinctInstitutionsWhen(string $skCodeColumn, string $conditionSql): string
    {
        $normalizedSk = "NULLIF(TRIM({$skCodeColumn}), '')";

        return "COUNT(DISTINCT CASE WHEN {$conditionSql} AND {$normalizedSk} IS NOT NULL THEN {$normalizedSk} END)";
    }

    /**
     * @return array<string, int>
     */
    private static function calculateWithoutYearFilter(Builder $baseQuery): array
    {
        $teacherTable = (new Teacher)->getTable();
        $teacherIdColumn = "{$teacherTable}.ID";
        $columnPrefix = "{$teacherTable}.";
        $cols = config('coach_teacher_support.columns');
        $rounds = config('coach_teacher_support.kpi_rounds', []);

        $selects = [
            DB::raw(self::sqlCountDistinctTeachersWhen($teacherIdColumn, '1 = 1').' as teacher_count'),
            DB::raw(self::sqlCountDistinctInstitutionsWhen($columnPrefix.'SK_Code', '1 = 1').' as institution_count'),
        ];

        foreach ($rounds as $key => $round) {
            $completedCol = $columnPrefix.$cols[$round['completed']];
            $selects[] = DB::raw(
                self::sqlCountDistinctTeachersWhen($teacherIdColumn, "{$completedCol} IS NOT NULL").' as '.$key
            );
        }

        $completedColumns = array_map(
            fn (array $round): string => $columnPrefix.$cols[$round['completed']],
            $rounds,
        );
        $completedCondition = $completedColumns === []
            ? '0 = 1'
            : '('.implode(' AND ', array_map(
                fn (string $column): string => "{$column} IS NOT NULL",
                $completedColumns,
            )).')';

        $selects[] = DB::raw(
            self::sqlCountDistinctTeachersWhen($teacherIdColumn, $completedCondition).' as completed'
        );
        $selects[] = DB::raw(
            self::sqlCountDistinctTeachersWhen(
                $teacherIdColumn,
                self::sqlAnyRoundUnsupportedWithoutYear($columnPrefix),
            ).' as unsupported'
        );

        $row = (clone $baseQuery)->select($selects)->toBase()->first();
        $result = [
            'teacher_count' => (int) ($row?->teacher_count ?? 0),
            'institution_count' => (int) ($row?->institution_count ?? 0),
        ];

        foreach (array_merge(self::roundKpiKeys(), ['completed', 'unsupported']) as $key) {
            $result[$key] = (int) ($row?->{$key} ?? 0);
        }

        return $result;
    }

    private static function sqlAnyRoundUnsupportedWithoutYear(string $columnPrefix): string
    {
        $cols = config('coach_teacher_support.columns');
        $clauses = [];

        foreach (config('coach_teacher_support.kpi_rounds', []) as $round) {
            $planCol = $columnPrefix.$cols[$round['plan']];
            $completedCol = $columnPrefix.$cols[$round['completed']];
            $clauses[] = "({$planCol} IS NOT NULL AND ({$completedCol} IS NULL))";
        }

        if ($clauses === []) {
            return '0 = 1';
        }

        return '('.implode(' OR ', $clauses).')';
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
        $result = [
            'teacher_count' => $rows->count(),
            'institution_count' => $rows
                ->pluck('SK_Code')
                ->filter(fn ($skCode): bool => trim((string) $skCode) !== '')
                ->unique()
                ->count(),
        ];

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
        $completedCol = self::qualifiedTeacherColumn($cols[$rounds[$kpiKey]['completed']]);
        $query->whereRaw(self::sqlColumnCompletedInYear($completedCol, $year));
    }

    public static function applyAllRoundsCompletedScope(Builder $query, int $year): void
    {
        $cols = config('coach_teacher_support.columns');
        $completedColumns = array_map(
            fn (array $round): string => self::qualifiedTeacherColumn($cols[$round['completed']]),
            config('coach_teacher_support.kpi_rounds', []),
        );
        $query->whereRaw(self::sqlAllRoundsCompletedInYear($completedColumns, $year));
    }

    public static function applyUnsupportedScope(Builder $query, int $year): void
    {
        $query->whereRaw(self::sqlAnyRoundUnsupportedInYear(self::teacherColumnPrefix(), $year));
    }

    private static function teacherColumnPrefix(): string
    {
        return (new Teacher)->getTable().'.';
    }

    private static function qualifiedTeacherColumn(string $column): string
    {
        return self::teacherColumnPrefix().$column;
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
