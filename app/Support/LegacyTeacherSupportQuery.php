<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 레거시 TR 교사 지원 테이블 — 교사 지원 현황 N차 완료 칸 fallback용 조회.
 */
final class LegacyTeacherSupportQuery
{
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
