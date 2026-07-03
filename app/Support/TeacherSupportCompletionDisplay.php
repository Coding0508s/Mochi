<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Support\Collection;

/**
 * 교사 지원 현황 메인 표의 N차 완료 칸 표시.
 *
 * Teachers._Nst_Support_Date 가 비어 있어도 MOCHI·레거시 완료 보고서가 있으면
 * 교사 상세(지원 내역)와 동일하게 보이도록 보완한다.
 */
final class TeacherSupportCompletionDisplay
{
    /**
     * @var array<string, list<array{date: string, type: string}>>
     */
    private static array $orphanReportsByTeacherYear = [];

    /**
     * @var array<string, array<int, array{date: string, type: string}>>
     */
    private static array $orphanAssignmentsCache = [];

    /**
     * 현재 페이지 교사들의 MOCHI·레거시 완료 보고서를 한 번에 적재한다.
     *
     * @param  Collection<int, Teacher>|iterable<int, Teacher>  $teachers
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

        $reportsByTeacher = self::orphanReportsForTeacherIds($teacherIds, $year);

        foreach ($teacherIds as $teacherId) {
            self::$orphanReportsByTeacherYear[self::cacheKey($teacherId, $year)] = $reportsByTeacher[$teacherId] ?? [];
        }
    }

    public static function flushRequestCache(): void
    {
        self::$orphanReportsByTeacherYear = [];
        self::$orphanAssignmentsCache = [];
    }

    /**
     * @return array{date: string, type: string}
     */
    public static function parts(Teacher $teacher, int $round, ?int $year): array
    {
        $parts = self::partsFromTeacherSlot($teacher, $round, $year);

        if ($parts['date'] !== '') {
            return $parts;
        }

        return self::orphanAssignments($teacher, $year)[$round] ?? ['date' => '', 'type' => ''];
    }

    public static function displayWithType(Teacher $teacher, int $round, ?int $year): string
    {
        $parts = self::parts($teacher, $round, $year);
        if ($parts['date'] === '') {
            return '';
        }

        if ($parts['type'] === '') {
            return $parts['date'];
        }

        return $parts['date'].' ('.$parts['type'].')';
    }

    /**
     * @return array<int, array{date: string, type: string}>
     */
    private static function orphanAssignments(Teacher $teacher, ?int $year): array
    {
        $cacheKey = $teacher->ID.':'.($year ?? 'all');

        if (array_key_exists($cacheKey, self::$orphanAssignmentsCache)) {
            return self::$orphanAssignmentsCache[$cacheKey];
        }

        $assignments = self::buildOrphanAssignments($teacher, $year);
        self::$orphanAssignmentsCache[$cacheKey] = $assignments;

        return $assignments;
    }

    /**
     * @return array<int, array{date: string, type: string}>
     */
    private static function buildOrphanAssignments(Teacher $teacher, ?int $year): array
    {
        $orphans = self::dedupeReports(
            self::excludeReportsMatchingTeacherSlots(
                $teacher,
                $year,
                self::completedOrphanReportsInYear((int) $teacher->ID, $year),
            ),
        );
        $assignments = [];
        $orphanIndex = 0;

        for ($round = 1; $round <= 4; $round++) {
            if ($orphanIndex >= count($orphans)) {
                break;
            }

            if (self::teacherRoundHasCompletionInYear($teacher, $round, $year)) {
                continue;
            }

            $report = $orphans[$orphanIndex++];
            $assignments[$round] = [
                'date' => $report['date'],
                'type' => $report['type'],
            ];
        }

        return $assignments;
    }

    /**
     * Teachers N차 완료 칸에 이미 표시되는 보고서는 다른 차수 고아 슬롯에 다시 넣지 않는다.
     *
     * @param  list<array{date: string, type: string}>  $reports
     * @return list<array{date: string, type: string}>
     */
    private static function excludeReportsMatchingTeacherSlots(Teacher $teacher, ?int $year, array $reports): array
    {
        $occupiedKeys = [];

        for ($round = 1; $round <= 4; $round++) {
            $parts = self::partsFromTeacherSlot($teacher, $round, $year);

            if ($parts['date'] === '') {
                continue;
            }

            $occupiedKeys[self::reportKey($parts['date'], $parts['type'])] = true;
        }

        return array_values(array_filter(
            $reports,
            fn (array $report): bool => ! isset($occupiedKeys[self::reportKey($report['date'], $report['type'])]),
        ));
    }

    /**
     * @param  list<array{date: string, type: string}>  $reports
     * @return list<array{date: string, type: string}>
     */
    private static function dedupeReports(array $reports): array
    {
        usort($reports, fn (array $left, array $right): int => strcmp($left['date'], $right['date']));

        $seen = [];
        $deduped = [];

        foreach ($reports as $report) {
            $key = self::reportKey($report['date'], $report['type']);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $report;
        }

        return $deduped;
    }

    private static function reportKey(string $date, string $type): string
    {
        return $date.'|'.trim($type);
    }

    private static function cacheKey(int $teacherId, ?int $year): string
    {
        return $teacherId.':'.($year ?? 'all');
    }

    /**
     * @return array{date: string, type: string}
     */
    private static function partsFromTeacherSlot(Teacher $teacher, int $round, ?int $year): array
    {
        $cols = config('coach_teacher_support.columns');
        $suffix = match ($round) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            default => null,
        };

        if ($suffix === null) {
            return ['date' => '', 'type' => ''];
        }

        $dateColumn = $cols["completed_{$suffix}"] ?? null;
        $typeColumn = $cols["type_{$suffix}"] ?? null;

        if ($dateColumn === null || $typeColumn === null) {
            return ['date' => '', 'type' => ''];
        }

        return ExcelSerialDate::completedDisplayParts(
            $teacher->getRawOriginal($dateColumn),
            $teacher->{$typeColumn},
            $year,
        );
    }

    private static function teacherRoundHasCompletionInYear(Teacher $teacher, int $round, ?int $year): bool
    {
        $cols = config('coach_teacher_support.columns');
        $suffix = match ($round) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            default => null,
        };

        if ($suffix === null) {
            return false;
        }

        $dateColumn = $cols["completed_{$suffix}"] ?? null;
        if ($dateColumn === null) {
            return false;
        }

        return ExcelSerialDate::matchesFilterYear($teacher->getRawOriginal($dateColumn), $year);
    }

    /**
     * @return list<array{date: string, type: string}>
     */
    private static function completedOrphanReportsInYear(int $teacherId, ?int $year): array
    {
        $cacheKey = self::cacheKey($teacherId, $year);

        if (array_key_exists($cacheKey, self::$orphanReportsByTeacherYear)) {
            return self::$orphanReportsByTeacherYear[$cacheKey];
        }

        $reports = self::orphanReportsForTeacherIds([$teacherId], $year)[$teacherId] ?? [];
        self::$orphanReportsByTeacherYear[$cacheKey] = $reports;

        return $reports;
    }

    /**
     * @param  list<int>  $teacherIds
     * @return array<int, list<array{date: string, type: string}>>
     */
    private static function orphanReportsForTeacherIds(array $teacherIds, ?int $year): array
    {
        $mochiReports = MochiTeacherSupportQuery::completedReportsForTeacherIds($teacherIds, $year);
        $legacyReports = LegacyTeacherSupportQuery::completedReportsForTeacherIds($teacherIds, $year);

        $result = [];

        foreach ($teacherIds as $teacherId) {
            $result[$teacherId] = self::dedupeReports(array_merge(
                $mochiReports[$teacherId] ?? [],
                $legacyReports[$teacherId] ?? [],
            ));
        }

        return $result;
    }
}
