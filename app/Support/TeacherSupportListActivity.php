<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 교사 지원 현황 목록 — 최신 지원 보기 정렬·필터.
 */
final class TeacherSupportListActivity
{
    /**
     * 실제 지원(완료·MOCHI 보고서)이 있는 교사만 남긴다. 계획만 있는 교사는 제외.
     *
     * @param  Builder<Teacher>  $query
     */
    public static function applyHasSupportHistoryScope(Builder $query, ?int $year): void
    {
        $query->where(function (Builder $outer) use ($year): void {
            $first = true;

            foreach (self::teacherCompletedDateColumns() as $column) {
                $clause = function (Builder $inner) use ($column, $year): void {
                    $inner->whereNotNull($column);

                    if ($year !== null) {
                        ExcelSerialDate::applyWhereYear($inner, $column, $year);
                    }
                };

                if ($first) {
                    $outer->where($clause);
                    $first = false;
                } else {
                    $outer->orWhere($clause);
                }
            }

            self::appendMochiReportExistsClause($outer, $year, $first);
        });
    }

    /**
     * 최신 지원일(완료·MOCHI) 내림차순 정렬. 계획일은 정렬에 쓰지 않는다.
     *
     * @param  Builder<Teacher>  $query
     */
    public static function applyLatestSupportOrdering(Builder $query, ?int $year): void
    {
        $mochiSubquery = MochiTeacherSupportQuery::latestDatePerTeacherSubquerySql($year);

        if ($mochiSubquery !== null) {
            $query->leftJoin(DB::raw($mochiSubquery.' AS mochi_latest_support'), function ($join): void {
                $join->on('mochi_latest_support.teacher_id', '=', 'Teachers.ID');
            });
        }

        $query->orderByDesc(DB::raw(self::latestSupportDateSqlExpression($year, $mochiSubquery !== null)))
            ->orderByDesc('Teachers.ID');
    }

    public static function latestSupportDateSqlExpression(?int $year, bool $mochiJoined = false): string
    {
        $parts = self::activityDateParts($year, $mochiJoined);

        if ($parts === []) {
            return "'1970-01-01'";
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $selects = array_map(
                fn (string $part): string => "SELECT {$part} AS activity_date",
                $parts,
            );

            return '(SELECT MAX(activity_date) FROM ('.implode(' UNION ALL ', $selects).') AS latest_support_dates)';
        }

        $coalesced = array_map(
            fn (string $part): string => "COALESCE({$part}, '1970-01-01')",
            $parts,
        );

        return 'GREATEST('.implode(', ', $coalesced).')';
    }

    /**
     * @return list<string>
     */
    private static function activityDateParts(?int $year, bool $mochiJoined): array
    {
        $parts = [];

        foreach (self::teacherCompletedDateColumns() as $column) {
            $qualified = 'Teachers.'.$column;
            $normalized = ExcelSerialDate::sqlNormalizedDateColumn($qualified);

            if ($year !== null) {
                $parts[] = 'CASE WHEN '.ExcelSerialDate::sqlColumnInYear($qualified, $year)
                    ." THEN {$normalized} END";
            } else {
                $parts[] = "CASE WHEN {$qualified} IS NOT NULL AND ".ExcelSerialDate::sqlDateValueIsNotBlank($qualified)
                    ." THEN {$normalized} END";
            }
        }

        if ($mochiJoined) {
            $parts[] = 'mochi_latest_support.latest_mochi_date';
        } elseif (($mochiSubquery = MochiTeacherSupportQuery::latestDatePerTeacherSubquerySql($year)) !== null) {
            $parts[] = "(SELECT latest_mochi_date FROM {$mochiSubquery} AS mochi_sort WHERE mochi_sort.teacher_id = Teachers.ID)";
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private static function teacherCompletedDateColumns(): array
    {
        $cols = config('coach_teacher_support.columns', []);
        $keys = [
            'completed_1st', 'completed_2nd', 'completed_3rd', 'completed_4th',
        ];

        return array_values(array_filter(array_map(
            fn (string $key): ?string => $cols[$key] ?? null,
            $keys,
        )));
    }

    /**
     * @param  Builder<Teacher>  $outer
     */
    private static function appendMochiReportExistsClause(Builder $outer, ?int $year, bool $first): void
    {
        $unionSql = MochiTeacherSupportQuery::teacherIdUnionSql($year);

        if ($unionSql === null) {
            return;
        }

        $clause = function (Builder $exists) use ($unionSql): void {
            $exists->whereExists(function ($sub) use ($unionSql): void {
                $sub->select(DB::raw(1))
                    ->from(DB::raw($unionSql.' AS mochi_teacher_ids'))
                    ->whereColumn('mochi_teacher_ids.teacher_id', 'Teachers.ID');
            });
        };

        if ($first) {
            $outer->where($clause);
        } else {
            $outer->orWhere($clause);
        }
    }
}
