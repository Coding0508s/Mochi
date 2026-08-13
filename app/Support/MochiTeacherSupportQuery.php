<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOCHI 교사 지원 보고서 테이블 — UNION·집계 SQL 생성.
 */
final class MochiTeacherSupportQuery
{
    /**
     * @return list<string>
     */
    public static function existingReportTables(): array
    {
        return array_values(array_filter(
            array_keys(config('coach_teacher_legacy_support.mochi_report_tables', [])),
            fn (string $table): bool => Schema::hasTable($table),
        ));
    }

    /**
     * 연도(또는 전체)에 보고서가 있는 teacher_id UNION SQL.
     */
    public static function teacherIdUnionSql(?int $year, ?int $month = null): ?string
    {
        $parts = [];

        foreach (self::existingReportTables() as $table) {
            $conditions = [
                "{$table}.teacher_id IS NOT NULL",
                "{$table}.support_date IS NOT NULL",
                self::sqlDateValueIsNotBlank("{$table}.support_date"),
            ];

            if ($month !== null) {
                $conditions[] = ExcelSerialDate::sqlColumnInYearMonth("{$table}.support_date", $year, $month);
            } elseif ($year !== null) {
                $conditions[] = ExcelSerialDate::sqlColumnInYear("{$table}.support_date", $year);
            }

            $parts[] = 'SELECT '.$table.'.teacher_id AS teacher_id FROM '.$table
                .' WHERE '.implode(' AND ', $conditions);
        }

        if ($parts === []) {
            return null;
        }

        return '('.implode(' UNION ', $parts).')';
    }

    /**
     * 교사별 최신 MOCHI support_date 서브쿼리 (LEFT JOIN 용).
     */
    public static function latestDatePerTeacherSubquerySql(?int $year): ?string
    {
        $rowsUnion = self::supportDateUnionSql($year);

        if ($rowsUnion === null) {
            return null;
        }

        return '(SELECT teacher_id, MAX(support_date) AS latest_mochi_date FROM ('
            .$rowsUnion
            .') AS mochi_support_rows GROUP BY teacher_id)';
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
            ->fromRaw($unionSql.' AS mochi_teacher_ids')
            ->distinct()
            ->pluck('teacher_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * 완료(status=완료) MOCHI 보고서를 교사 ID별로 한 번에 조회한다.
     *
     * @param  list<int>  $teacherIds
     * @return array<int, list<array{date: string, type: string}>>
     */
    public static function completedReportsForTeacherIds(array $teacherIds, ?int $year): array
    {
        if ($teacherIds === []) {
            return [];
        }

        $unionSql = self::completedReportUnionSql($year);

        if ($unionSql === null) {
            return [];
        }

        $rows = DB::query()
            ->fromRaw($unionSql.' AS mochi_completed_reports')
            ->whereIn('teacher_id', $teacherIds)
            ->orderBy('teacher_id')
            ->orderBy('support_date')
            ->get(['teacher_id', 'support_date', 'type_label']);

        $grouped = [];

        foreach ($rows as $row) {
            $date = ExcelSerialDate::toStorageString($row->support_date);

            if ($date === null) {
                continue;
            }

            $teacherId = (int) $row->teacher_id;
            $grouped[$teacherId][] = [
                'date' => $date,
                'type' => (string) $row->type_label,
                'sort' => Carbon::parse($date)->getTimestamp(),
            ];
        }

        $result = [];

        foreach ($grouped as $teacherId => $reports) {
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

    private static function completedReportUnionSql(?int $year): ?string
    {
        $typeLabels = config('coach_teacher_legacy_support.mochi_report_tables', []);
        $parts = [];

        foreach (self::existingReportTables() as $table) {
            $conditions = [
                "{$table}.teacher_id IS NOT NULL",
                "{$table}.support_date IS NOT NULL",
                self::sqlDateValueIsNotBlank("{$table}.support_date"),
            ];

            if ($year !== null) {
                $conditions[] = ExcelSerialDate::sqlColumnInYear("{$table}.support_date", $year);
            }

            if (Schema::hasColumn($table, 'status')) {
                $conditions[] = "{$table}.status = '완료'";
            }

            $typeLabel = str_replace("'", "''", (string) ($typeLabels[$table] ?? ''));

            $parts[] = 'SELECT '.$table.'.teacher_id AS teacher_id, '.$table.'.support_date AS support_date, '
                ."'{$typeLabel}' AS type_label FROM ".$table
                .' WHERE '.implode(' AND ', $conditions);
        }

        if ($parts === []) {
            return null;
        }

        return '('.implode(' UNION ALL ', $parts).')';
    }

    private static function supportDateUnionSql(?int $year): ?string
    {
        $parts = [];

        foreach (self::existingReportTables() as $table) {
            $conditions = [
                "{$table}.teacher_id IS NOT NULL",
                "{$table}.support_date IS NOT NULL",
                self::sqlDateValueIsNotBlank("{$table}.support_date"),
            ];

            if ($year !== null) {
                $conditions[] = ExcelSerialDate::sqlColumnInYear("{$table}.support_date", $year);
            }

            $parts[] = 'SELECT '.$table.'.teacher_id AS teacher_id, '.$table.'.support_date AS support_date FROM '.$table
                .' WHERE '.implode(' AND ', $conditions);
        }

        if ($parts === []) {
            return null;
        }

        return '('.implode(' UNION ALL ', $parts).')';
    }

    private static function sqlDateValueIsNotBlank(string $qualifiedColumn): string
    {
        return ExcelSerialDate::sqlDateValueIsNotBlank($qualifiedColumn);
    }
}
