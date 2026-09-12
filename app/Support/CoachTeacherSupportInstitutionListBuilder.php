<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CoachTeacherSupportInstitutionListBuilder
{
    /**
     * @param  Builder<Teacher>  $baseQuery
     * @return array{
     *     teacher_ids: list<int>,
     *     rowspans_by_teacher_id: array<int, int>,
     *     group_count: int,
     *     teacher_count: int
     * }
     */
    public function build(Builder $baseQuery, ?int $year): array
    {
        $supportCondition = TeacherSupportListActivity::supportHistorySqlCondition($year);
        $latestSupportExpression = TeacherSupportListActivity::latestSupportDateSqlExpression($year);

        /** @var Collection<int, object{ID: int|string, Name: string|null, SK_Code: string|null, has_support: int|string|null, latest_support_at: string|null}> $rows */
        $rows = (clone $baseQuery)
            ->select([
                'Teachers.ID',
                'Teachers.Name',
                'Teachers.SK_Code',
            ])
            ->selectRaw("CASE WHEN {$supportCondition} THEN 1 ELSE 0 END AS has_support")
            ->selectRaw("{$latestSupportExpression} AS latest_support_at")
            ->get();

        /** @var array<string, list<array{id: int, name: string, sk: string, section: 'supported'|'unsupported', latest_support_at: string|null, latest_support_ts: int}>> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $teacherId = (int) $row->ID;
            $section = (int) ($row->has_support ?? 0) === 1 ? 'supported' : 'unsupported';
            $latestSupportAt = $this->normalizedLatestSupportAt($row->latest_support_at);
            $normalizedSk = SkCodeNormalizer::normalize((string) ($row->SK_Code ?? ''));
            $groupKey = $normalizedSk ?? '__none__:'.$teacherId;

            $grouped[$groupKey][] = [
                'id' => $teacherId,
                'name' => trim((string) ($row->Name ?? '')),
                'sk' => $normalizedSk ?? '',
                'section' => $section,
                'latest_support_at' => $latestSupportAt,
                'latest_support_ts' => $latestSupportAt !== null ? ($this->latestSupportTimestamp($latestSupportAt)) : 0,
            ];
        }

        foreach ($grouped as &$teacherRows) {
            usort($teacherRows, $this->compareTeachers(...));
        }
        unset($teacherRows);

        $institutions = [];
        foreach ($grouped as $groupKey => $teacherRows) {
            $hasSupport = false;
            $maxTs = 0;
            foreach ($teacherRows as $teacherRow) {
                if ($teacherRow['section'] === 'supported') {
                    $hasSupport = true;
                }
                $maxTs = max($maxTs, $teacherRow['latest_support_ts']);
            }

            $institutions[] = [
                'key' => $groupKey,
                'sk' => $teacherRows[0]['sk'] ?? '',
                'has_support' => $hasSupport,
                'max_ts' => $maxTs,
                'teachers' => $teacherRows,
            ];
        }

        usort($institutions, function (array $left, array $right): int {
            if ($left['has_support'] !== $right['has_support']) {
                return $left['has_support'] ? -1 : 1;
            }

            if ($left['max_ts'] !== $right['max_ts']) {
                return $right['max_ts'] <=> $left['max_ts'];
            }

            if ($left['sk'] !== $right['sk']) {
                return strcmp($left['sk'], $right['sk']);
            }

            return strcmp($left['key'], $right['key']);
        });

        $teacherIds = [];
        $rowspansByTeacherId = [];

        foreach ($institutions as $institution) {
            $span = count($institution['teachers']);
            foreach ($institution['teachers'] as $index => $teacherRow) {
                $teacherIds[] = $teacherRow['id'];
                $rowspansByTeacherId[$teacherRow['id']] = $index === 0 ? $span : 0;
            }
        }

        return [
            'teacher_ids' => $teacherIds,
            'rowspans_by_teacher_id' => $rowspansByTeacherId,
            'group_count' => count($institutions),
            'teacher_count' => count($teacherIds),
        ];
    }

    /**
     * @param  array{id: int, name: string, section: 'supported'|'unsupported', latest_support_at: string|null, latest_support_ts: int}  $left
     * @param  array{id: int, name: string, section: 'supported'|'unsupported', latest_support_at: string|null, latest_support_ts: int}  $right
     */
    private function compareTeachers(array $left, array $right): int
    {
        $leftDate = $left['latest_support_at'] ?? '';
        $rightDate = $right['latest_support_at'] ?? '';

        if ($leftDate !== $rightDate) {
            if ($leftDate === '') {
                return 1;
            }

            if ($rightDate === '') {
                return -1;
            }

            return strcmp($rightDate, $leftDate);
        }

        if ($left['section'] !== $right['section']) {
            return $left['section'] === 'supported' ? -1 : 1;
        }

        if ($left['name'] !== $right['name']) {
            return strcmp($left['name'], $right['name']);
        }

        return $left['id'] <=> $right['id'];
    }

    private function normalizedLatestSupportAt(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = ExcelSerialDate::toStorageString($value);

        if ($date === null || $date === '' || $date === '1970-01-01') {
            return null;
        }

        return $date;
    }

    private function latestSupportTimestamp(string $date): int
    {
        return strtotime($date) ?: 0;
    }
}
