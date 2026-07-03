<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 레거시 TR 교사 지원 테이블 — 교사 지원 현황 N차 완료 칸·최신 지원 보기 조회.
 */
final class LegacyTeacherSupportQuery
{
    /**
     * 연도(또는 전체)에 완료 지원이 있는 teacher_id UNION SQL.
     */
    public static function teacherIdUnionSql(?int $year): ?string
    {
        $parts = [];

        foreach (self::completionSources() as $source) {
            $select = self::teacherIdSelectSql($source, $year);

            if ($select !== null) {
                $parts[] = $select;
            }
        }

        if ($parts === []) {
            return null;
        }

        return '('.implode(' UNION ', $parts).')';
    }

    /**
     * @return list<int>
     */
    public static function teacherIdsInYear(int $year): array
    {
        $unionSql = self::teacherIdUnionSql($year);

        if ($unionSql === null) {
            return [];
        }

        return DB::query()
            ->fromRaw($unionSql.' AS legacy_teacher_ids')
            ->distinct()
            ->pluck('teacher_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * 교사별 최신 레거시 SupportDate 서브쿼리 (LEFT JOIN 용).
     */
    public static function latestDatePerTeacherSubquerySql(?int $year): ?string
    {
        $rowsUnion = self::supportDateUnionSql($year);

        if ($rowsUnion === null) {
            return null;
        }

        return '(SELECT teacher_id, MAX(support_date) AS latest_legacy_date FROM ('
            .$rowsUnion
            .') AS legacy_support_rows GROUP BY teacher_id)';
    }

    /**
     * @param  list<int>  $teacherIds
     * @return array<int, list<array{date: string, type: string}>>
     */
    public static function completedReportsForTeacherIds(array $teacherIds, ?int $year): array
    {
        if ($teacherIds === []) {
            return [];
        }

        $reportsByTeacher = [];

        foreach (config('coach_teacher_legacy_support.legacy_completion_sources', []) as $source) {
            $table = $source['table'] ?? null;
            $typeLabel = $source['type'] ?? '';

            if (! is_string($table) || $table === '' || ! Schema::hasTable($table)) {
                continue;
            }

            $teacherIdColumn = self::teacherIdColumn($table);

            if ($teacherIdColumn === null || ! Schema::hasColumn($table, 'SupportDate')) {
                continue;
            }

            $query = DB::table($table)
                ->whereIn($teacherIdColumn, $teacherIds)
                ->whereNotNull('SupportDate')
                ->whereRaw(ExcelSerialDate::sqlDateValueIsNotBlank('SupportDate'));

            if ($year !== null) {
                $query->whereRaw(ExcelSerialDate::sqlColumnInYear('SupportDate', $year));
            }

            if (Schema::hasColumn($table, 'Status')) {
                $query->where('Status', '완료');
            }

            $rows = $query
                ->orderBy($teacherIdColumn)
                ->orderBy('SupportDate')
                ->get([$teacherIdColumn, 'SupportDate']);

            foreach ($rows as $row) {
                $date = ExcelSerialDate::toStorageString($row->SupportDate);

                if ($date === null) {
                    continue;
                }

                $teacherId = (int) $row->{$teacherIdColumn};
                $reportsByTeacher[$teacherId][] = [
                    'date' => $date,
                    'type' => (string) $typeLabel,
                    'sort' => Carbon::parse($date)->getTimestamp(),
                ];
            }
        }

        $result = [];

        foreach ($reportsByTeacher as $teacherId => $reports) {
            usort($reports, fn (array $left, array $right): int => $left['sort'] <=> $right['sort']);
            $result[$teacherId] = array_map(
                fn (array $report): array => [
                    'date' => $report['date'],
                    'type' => $report['type'],
                ],
                $reports,
            );
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function completionSources(): array
    {
        return config('coach_teacher_legacy_support.legacy_completion_sources', []);
    }

    private static function supportDateUnionSql(?int $year): ?string
    {
        $parts = [];

        foreach (self::completionSources() as $source) {
            $select = self::supportDateSelectSql($source, $year);

            if ($select !== null) {
                $parts[] = $select;
            }
        }

        if ($parts === []) {
            return null;
        }

        return '('.implode(' UNION ALL ', $parts).')';
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private static function teacherIdSelectSql(array $source, ?int $year): ?string
    {
        $table = $source['table'] ?? null;

        if (! is_string($table) || $table === '' || ! Schema::hasTable($table)) {
            return null;
        }

        $teacherIdColumn = self::teacherIdColumn($table);

        if ($teacherIdColumn === null || ! Schema::hasColumn($table, 'SupportDate')) {
            return null;
        }

        $conditions = self::completedRowConditions($table, $year);

        return 'SELECT '.$table.'.'.$teacherIdColumn.' AS teacher_id FROM '.$table
            .' WHERE '.implode(' AND ', $conditions);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private static function supportDateSelectSql(array $source, ?int $year): ?string
    {
        $table = $source['table'] ?? null;

        if (! is_string($table) || $table === '' || ! Schema::hasTable($table)) {
            return null;
        }

        $teacherIdColumn = self::teacherIdColumn($table);

        if ($teacherIdColumn === null || ! Schema::hasColumn($table, 'SupportDate')) {
            return null;
        }

        $conditions = self::completedRowConditions($table, $year);

        return 'SELECT '.$table.'.'.$teacherIdColumn.' AS teacher_id, '.$table.'.SupportDate AS support_date FROM '.$table
            .' WHERE '.implode(' AND ', $conditions);
    }

    /**
     * @return list<string>
     */
    private static function completedRowConditions(string $table, ?int $year): array
    {
        $teacherIdColumn = self::teacherIdColumn($table);

        $conditions = [
            "{$table}.{$teacherIdColumn} IS NOT NULL",
            "{$table}.SupportDate IS NOT NULL",
            ExcelSerialDate::sqlDateValueIsNotBlank("{$table}.SupportDate"),
        ];

        if ($year !== null) {
            $conditions[] = ExcelSerialDate::sqlColumnInYear("{$table}.SupportDate", $year);
        }

        if (Schema::hasColumn($table, 'Status')) {
            $conditions[] = "{$table}.Status = '완료'";
        }

        return $conditions;
    }

    private static function teacherIdColumn(string $table): ?string
    {
        foreach (['TeacherId', 'TeacherID'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }
}
