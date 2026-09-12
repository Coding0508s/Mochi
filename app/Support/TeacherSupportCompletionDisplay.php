<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Support\Collection;

/**
 * 교사 지원 현황 메인 표의 N차 완료 칸 표시.
 *
 * Teachers._Nst_Support_Date 가 비어 있어도 MOCHI·레거시 완료 보고서가 있으면
 * 교사 상세(지원 내역)와 동일하게 보이도록 보완한다.
 *
 * 동일 지원일(타입이 달라도) 완료가 여러 건이면 해당 차수에 「외 N건」을 붙인다.
 */
final class TeacherSupportCompletionDisplay
{
    /**
     * @var array<string, list<array{date: string, type: string, count: int, detail_key: string}>>
     */
    private static array $orphanReportsByTeacherYear = [];

    /**
     * @var array<string, array<int, array{date: string, type: string, count: int, detail_key: string}>>
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
     * @return array{date: string, type: string, extra: int, detail_key: string}
     */
    public static function parts(Teacher $teacher, int $round, ?int $year): array
    {
        $parts = self::partsFromTeacherSlot($teacher, $round, $year);

        if ($parts['date'] === '') {
            $parts = self::orphanAssignments($teacher, $year)[$round] ?? [
                'date' => '',
                'type' => '',
                'count' => 1,
                'detail_key' => '',
            ];
        }

        if ($parts['date'] === '') {
            return ['date' => '', 'type' => '', 'extra' => 0, 'detail_key' => ''];
        }

        $count = isset($parts['count'])
            ? (int) $parts['count']
            : self::reportCountForDate($teacher, $year, $parts['date']);

        $detailKey = (string) ($parts['detail_key'] ?? '');
        if ($detailKey === '') {
            $matched = self::matchingReport($teacher, $year, $parts['date'], $parts['type'] ?? '');
            $detailKey = (string) ($matched['detail_key'] ?? '');
        }

        if (! self::isTeacherSupportDetailKey($detailKey)) {
            return ['date' => '', 'type' => '', 'extra' => 0, 'detail_key' => ''];
        }

        return [
            'date' => $parts['date'],
            'type' => $parts['type'],
            'extra' => max(0, $count - 1),
            'detail_key' => $detailKey,
        ];
    }

    public static function displayWithType(Teacher $teacher, int $round, ?int $year): string
    {
        $parts = self::parts($teacher, $round, $year);
        if ($parts['date'] === '') {
            return '';
        }

        $base = $parts['type'] === ''
            ? $parts['date']
            : $parts['date'].' ('.$parts['type'].')';

        if ($parts['extra'] <= 0) {
            return $base;
        }

        return $base.' 외 '.$parts['extra'].'건';
    }

    /**
     * @return array<int, array{date: string, type: string, count: int, detail_key: string}>
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
     * @return array<int, array{date: string, type: string, count: int, detail_key: string}>
     */
    private static function buildOrphanAssignments(Teacher $teacher, ?int $year): array
    {
        $orphans = self::excludeReportsMatchingTeacherSlots(
            $teacher,
            $year,
            self::completedOrphanReportsInYear((int) $teacher->ID, $year),
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
                'count' => $report['count'],
                'detail_key' => $report['detail_key'] ?? '',
            ];
        }

        return $assignments;
    }

    /**
     * Teachers N차 완료 칸에 이미 표시되는 지원일(날짜)은 다른 차수 고아 슬롯에 다시 넣지 않는다.
     *
     * @param  list<array{date: string, type: string, count: int, detail_key?: string}>  $reports
     * @return list<array{date: string, type: string, count: int, detail_key: string}>
     */
    private static function excludeReportsMatchingTeacherSlots(Teacher $teacher, ?int $year, array $reports): array
    {
        $occupiedDates = [];

        for ($round = 1; $round <= 4; $round++) {
            $parts = self::partsFromTeacherSlot($teacher, $round, $year);

            if ($parts['date'] === '') {
                continue;
            }

            $occupiedDates[$parts['date']] = true;
        }

        return array_values(array_filter(
            $reports,
            fn (array $report): bool => ! isset($occupiedDates[$report['date']]),
        ));
    }

    /**
     * 같은 지원일은 타입과 무관하게 한 칸으로 묶는다. 대표 타입은 첫 번째 건을 쓴다.
     *
     * @param  list<array{date: string, type: string, count?: int, detail_key?: string}>  $reports
     * @return list<array{date: string, type: string, count: int, detail_key: string}>
     */
    private static function dedupeReports(array $reports): array
    {
        usort($reports, function (array $left, array $right): int {
            $byDate = strcmp($left['date'], $right['date']);

            if ($byDate !== 0) {
                return $byDate;
            }

            return strcmp(trim($left['type']), trim($right['type']));
        });

        $seen = [];
        $deduped = [];

        foreach ($reports as $report) {
            $key = $report['date'];
            $addCount = max(1, (int) ($report['count'] ?? 1));

            if (isset($seen[$key])) {
                $deduped[$seen[$key]]['count'] += $addCount;

                continue;
            }

            $seen[$key] = count($deduped);
            $deduped[] = [
                'date' => $report['date'],
                'type' => $report['type'],
                'count' => $addCount,
                'detail_key' => (string) ($report['detail_key'] ?? ''),
            ];
        }

        return $deduped;
    }

    private static function cacheKey(int $teacherId, ?int $year): string
    {
        return $teacherId.':'.($year ?? 'all');
    }

    private static function reportCountForDate(Teacher $teacher, ?int $year, string $date): int
    {
        foreach (self::completedOrphanReportsInYear((int) $teacher->ID, $year) as $report) {
            if ($report['date'] === $date) {
                return max(1, (int) $report['count']);
            }
        }

        return 1;
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

        $parts = ExcelSerialDate::completedDisplayParts(
            $teacher->getRawOriginal($dateColumn),
            $teacher->{$typeColumn},
            $year,
        );

        if (TeacherSupportNewTeacherDisplay::isNewTeacherSupportType($parts['type'] ?? null)) {
            return ['date' => '', 'type' => ''];
        }

        if (($parts['date'] ?? '') === '') {
            return ['date' => '', 'type' => ''];
        }

        $matched = self::matchingReport($teacher, $year, $parts['date'], $parts['type'] ?? '');
        if ($matched === null || ! self::isTeacherSupportDetailKey((string) ($matched['detail_key'] ?? ''))) {
            return ['date' => '', 'type' => ''];
        }

        return [
            'date' => $parts['date'],
            'type' => $parts['type'],
            'detail_key' => $matched['detail_key'],
        ];
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

        return self::partsFromTeacherSlot($teacher, $round, $year)['date'] !== '';
    }

    /**
     * @return list<array{date: string, type: string, count: int, detail_key: string}>
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
     * @return array<int, list<array{date: string, type: string, count: int, detail_key: string}>>
     */
    private static function orphanReportsForTeacherIds(array $teacherIds, ?int $year): array
    {
        $mochiReports = MochiTeacherSupportQuery::completedReportsForTeacherIds($teacherIds, $year);
        $legacyReports = LegacyTeacherSupportQuery::completedReportsForTeacherIds($teacherIds, $year);

        $result = [];

        foreach ($teacherIds as $teacherId) {
            $filteredReports = array_values(array_filter(array_merge(
                $mochiReports[$teacherId] ?? [],
                $legacyReports[$teacherId] ?? [],
            ), fn (array $report): bool => ! TeacherSupportNewTeacherDisplay::isNewTeacherSupportType(
                $report['type'] ?? null,
            )));

            $result[$teacherId] = self::dedupeReports($filteredReports);
        }

        return $result;
    }

    /**
     * 같은 지원일의 교사 지원 보고서만 연결한다.
     * 기관 지원(account:)은 차수 칸에 넣지 않는다.
     *
     * @return array{date: string, type: string, count?: int, detail_key: string}|null
     */
    private static function matchingReport(Teacher $teacher, ?int $year, string $date, string $type): ?array
    {
        $reports = self::completedOrphanReportsInYear((int) $teacher->ID, $year);
        $dateMatches = array_values(array_filter(
            $reports,
            fn (array $report): bool => $report['date'] === $date
                && self::isTeacherSupportDetailKey((string) ($report['detail_key'] ?? '')),
        ));

        if ($dateMatches === []) {
            return null;
        }

        $type = trim($type);
        if ($type !== '') {
            foreach ($dateMatches as $report) {
                if (trim($report['type']) === $type) {
                    return $report;
                }
            }
        }

        return $dateMatches[0];
    }

    private static function isTeacherSupportDetailKey(string $detailKey): bool
    {
        return str_starts_with($detailKey, 'mochi:') || str_starts_with($detailKey, 'legacy:');
    }
}
