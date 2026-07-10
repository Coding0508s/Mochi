<?php

namespace App\Support;

use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 교사 지원 현황 메인 표의 신규교사 전용 칸 표시.
 */
final class TeacherSupportNewTeacherDisplay
{
    /**
     * @var array<string, array{date: string, type: string}>
     */
    private static array $partsByTeacherYear = [];

    /**
     * @param  iterable<int, Teacher>  $teachers
     */
    public static function preloadForTeachers(iterable $teachers, ?int $year): void
    {
        $teacherIds = collect($teachers)
            ->pluck('ID')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($teacherIds === []) {
            return;
        }

        $partsByTeacher = self::partsForTeacherIds($teacherIds, $year);

        foreach ($teacherIds as $teacherId) {
            self::$partsByTeacherYear[self::cacheKey($teacherId, $year)] = $partsByTeacher[$teacherId] ?? ['date' => '', 'type' => ''];
        }
    }

    public static function flushRequestCache(): void
    {
        self::$partsByTeacherYear = [];
    }

    /**
     * @return array{date: string, type: string}
     */
    public static function parts(Teacher $teacher, ?int $year): array
    {
        $cacheKey = self::cacheKey((int) $teacher->ID, $year);

        if (array_key_exists($cacheKey, self::$partsByTeacherYear)) {
            return self::$partsByTeacherYear[$cacheKey];
        }

        $parts = self::partsForTeacherIds([(int) $teacher->ID], $year)[(int) $teacher->ID] ?? ['date' => '', 'type' => ''];
        self::$partsByTeacherYear[$cacheKey] = $parts;

        return $parts;
    }

    public static function displayWithType(Teacher $teacher, ?int $year): string
    {
        $parts = self::parts($teacher, $year);

        if ($parts['date'] === '') {
            return '';
        }

        if ($parts['type'] === '') {
            return $parts['date'];
        }

        return $parts['date'].' ('.$parts['type'].')';
    }

    public static function isNewTeacherSupportType(?string $type): bool
    {
        $normalizedType = trim((string) $type);

        if ($normalizedType === '') {
            return false;
        }

        return in_array($normalizedType, self::supportTypes(), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function supportTypeForPayload(array $payload, string $defaultType): string
    {
        if ((bool) ($payload['is_new_teacher_support'] ?? false)) {
            return self::supportTypes()[0] ?? '교사 지원(신규교사)';
        }

        return $defaultType;
    }

    /**
     * @param  list<int>  $teacherIds
     * @return array<int, array{date: string, type: string}>
     */
    private static function partsForTeacherIds(array $teacherIds, ?int $year): array
    {
        $reports = [];

        foreach (self::demoLessonReportsForTeacherIds($teacherIds, $year) as $teacherId => $teacherReports) {
            $reports[$teacherId] = array_merge($reports[$teacherId] ?? [], $teacherReports);
        }

        foreach (self::linkedNewTeacherReportsForTeacherIds($teacherIds, $year) as $teacherId => $teacherReports) {
            $reports[$teacherId] = array_merge($reports[$teacherId] ?? [], $teacherReports);
        }

        foreach (LegacyTeacherSupportQuery::completedReportsForTeacherIds($teacherIds, $year) as $teacherId => $teacherReports) {
            $reports[$teacherId] = array_merge($reports[$teacherId] ?? [], $teacherReports);
        }

        $result = [];

        foreach ($teacherIds as $teacherId) {
            $teacherReports = $reports[$teacherId] ?? [];

            if ($teacherReports === []) {
                $result[$teacherId] = ['date' => '', 'type' => ''];

                continue;
            }

            usort($teacherReports, fn (array $left, array $right): int => strcmp($right['date'], $left['date']));

            $result[$teacherId] = [
                'date' => $teacherReports[0]['date'],
                'type' => $teacherReports[0]['type'],
            ];
        }

        return $result;
    }

    /**
     * @param  list<int>  $teacherIds
     * @return array<int, list<array{date: string, type: string}>>
     */
    private static function linkedNewTeacherReportsForTeacherIds(array $teacherIds, ?int $year): array
    {
        if (! Schema::hasTable('S_SupportInfo_Account')) {
            return [];
        }

        $result = [];
        $typeLabels = config('coach_teacher_legacy_support.mochi_report_tables', []);

        foreach (MochiTeacherSupportQuery::existingReportTables() as $table) {
            if (! Schema::hasColumn($table, 'support_record_id')) {
                continue;
            }

            $query = DB::table($table)
                ->join('S_SupportInfo_Account', 'S_SupportInfo_Account.ID', '=', "{$table}.support_record_id")
                ->whereIn("{$table}.teacher_id", $teacherIds)
                ->whereIn('S_SupportInfo_Account.Support_Type', self::supportTypes())
                ->whereNotNull("{$table}.support_date")
                ->whereRaw(ExcelSerialDate::sqlDateValueIsNotBlank("{$table}.support_date"));

            if ($year !== null) {
                $query->whereRaw(ExcelSerialDate::sqlColumnInYear("{$table}.support_date", $year));
            }

            if (Schema::hasColumn($table, 'status')) {
                $query->where("{$table}.status", '완료');
            }

            $rows = $query
                ->orderBy("{$table}.teacher_id")
                ->orderBy("{$table}.support_date")
                ->get([
                    "{$table}.teacher_id",
                    "{$table}.support_date",
                    'S_SupportInfo_Account.Support_Type',
                ]);

            foreach ($rows as $row) {
                $date = ExcelSerialDate::toStorageString($row->support_date);

                if ($date === null) {
                    continue;
                }

                $result[(int) $row->teacher_id][] = [
                    'date' => $date,
                    'type' => (string) ($row->Support_Type ?: ($typeLabels[$table] ?? '')),
                ];
            }
        }

        return $result;
    }

    /**
     * @param  list<int>  $teacherIds
     * @return array<int, list<array{date: string, type: string}>>
     */
    private static function demoLessonReportsForTeacherIds(array $teacherIds, ?int $year): array
    {
        $table = 'teacher_demo_lesson_support_reports';

        if (! Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table)
            ->whereIn('teacher_id', $teacherIds)
            ->whereNotNull('support_date')
            ->whereRaw(ExcelSerialDate::sqlDateValueIsNotBlank('support_date'));

        if ($year !== null) {
            $query->whereRaw(ExcelSerialDate::sqlColumnInYear('support_date', $year));
        }

        if (Schema::hasColumn($table, 'status')) {
            $query->where('status', '완료');
        }

        $rows = $query
            ->orderBy('teacher_id')
            ->orderBy('support_date')
            ->get(['teacher_id', 'support_date']);

        $result = [];

        foreach ($rows as $row) {
            $date = ExcelSerialDate::toStorageString($row->support_date);

            if ($date === null) {
                continue;
            }

            $result[(int) $row->teacher_id][] = [
                'date' => $date,
                'type' => (string) config('coach_teacher_demo_lesson.support_type_label', '신규교사 시연수업'),
                'sort' => Carbon::parse($date)->getTimestamp(),
            ];
        }

        return array_map(
            fn (array $reports): array => array_map(
                fn (array $report): array => [
                    'date' => $report['date'],
                    'type' => $report['type'],
                ],
                $reports,
            ),
            $result,
        );
    }

    /**
     * @return list<string>
     */
    /**
     * @return list<string>
     */
    public static function supportTypes(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $type): string => trim((string) $type),
            config('coach_teacher_support.new_teacher_support_types', []),
        )));
    }

    private static function cacheKey(int $teacherId, ?int $year): string
    {
        return $teacherId.':'.($year ?? 'all');
    }
}
