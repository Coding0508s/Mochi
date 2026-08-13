<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CoachTeacherSupportInstitutionListBuilder
{
    /**
     * @param  Builder<Teacher>  $baseQuery
     * @return array{
     *     teacher_ids: list<int>,
     *     rowspans_by_teacher_id: array<int, int>,
     *     group_count: int,
     *     paginator: LengthAwarePaginator<int, array{
     *         section: 'supported'|'unsupported',
     *         institution_key: string,
     *         institution_name: string,
     *         teacher_rows: list<array{id: int, name: string, latest_support_at: string|null}>
     *     }>
     * }
     */
    public function build(Builder $baseQuery, ?int $year, int $page, int $groupsPerPage = 50): array
    {
        $supportCondition = TeacherSupportListActivity::supportHistorySqlCondition($year);
        $latestSupportExpression = TeacherSupportListActivity::latestSupportDateSqlExpression($year);

        /** @var Collection<int, object{ID: int|string, SK_Code: string|null, Name: string|null, School_Name: string|null, has_support: int|string|null, latest_support_at: string|null}> $rows */
        $rows = (clone $baseQuery)
            ->select([
                'Teachers.ID',
                'Teachers.SK_Code',
                'Teachers.Name',
                'Teachers.School_Name',
            ])
            ->selectRaw("CASE WHEN {$supportCondition} THEN 1 ELSE 0 END AS has_support")
            ->selectRaw("CASE WHEN {$supportCondition} THEN {$latestSupportExpression} ELSE NULL END AS latest_support_at")
            ->get();

        $groups = [];

        foreach ($rows as $row) {
            $teacherId = (int) $row->ID;
            $section = (int) ($row->has_support ?? 0) === 1 ? 'supported' : 'unsupported';
            $institutionKey = SkCodeNormalizer::normalize($row->SK_Code) ?? ('teacher-'.$teacherId);
            $institutionName = trim((string) ($row->School_Name ?? ''));
            if ($institutionName === '') {
                $institutionName = trim((string) ($row->Name ?? '미분류 기관'));
            }

            $groupKey = $section.':'.$institutionKey;

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'section' => $section,
                    'institution_key' => $institutionKey,
                    'institution_name' => $institutionName,
                    'teacher_rows' => [],
                    'max_latest_at' => null,
                ];
            }

            $latestSupportAt = $row->latest_support_at !== null ? (string) $row->latest_support_at : null;

            $groups[$groupKey]['teacher_rows'][] = [
                'id' => $teacherId,
                'name' => trim((string) ($row->Name ?? '')),
                'latest_support_at' => $latestSupportAt,
            ];

            if ($section === 'supported' && $latestSupportAt !== null) {
                $timestamp = strtotime($latestSupportAt) ?: 0;
                $currentMax = $groups[$groupKey]['max_latest_at'];
                if ($currentMax === null || $timestamp > $currentMax) {
                    $groups[$groupKey]['max_latest_at'] = $timestamp;
                }
            }
        }

        foreach ($groups as &$group) {
            usort(
                $group['teacher_rows'],
                function (array $left, array $right) use ($group): int {
                    if ($group['section'] === 'supported') {
                        $leftTs = $left['latest_support_at'] ? (strtotime($left['latest_support_at']) ?: 0) : 0;
                        $rightTs = $right['latest_support_at'] ? (strtotime($right['latest_support_at']) ?: 0) : 0;
                        if ($leftTs !== $rightTs) {
                            return $rightTs <=> $leftTs;
                        }
                    }

                    return strcmp($left['name'], $right['name']);
                },
            );
        }
        unset($group);

        $supportedGroups = [];
        $unsupportedGroups = [];
        foreach ($groups as $group) {
            if ($group['section'] === 'supported') {
                $supportedGroups[] = $group;
            } else {
                $unsupportedGroups[] = $group;
            }
        }

        usort($supportedGroups, function (array $left, array $right): int {
            $leftMax = $left['max_latest_at'] ?? 0;
            $rightMax = $right['max_latest_at'] ?? 0;
            if ($leftMax !== $rightMax) {
                return $rightMax <=> $leftMax;
            }

            return strcmp($left['institution_name'], $right['institution_name']);
        });

        usort($unsupportedGroups, function (array $left, array $right): int {
            return strcmp($left['institution_name'], $right['institution_name']);
        });

        $orderedGroups = array_values(array_merge($supportedGroups, $unsupportedGroups));
        $groupCollection = collect($orderedGroups);
        $groupCount = $groupCollection->count();

        $page = max(1, $page);
        $groupsPerPage = max(1, $groupsPerPage);
        $pagedGroups = $groupCollection->forPage($page, $groupsPerPage)->values();

        $teacherIds = [];
        $rowspansByTeacherId = [];

        foreach ($pagedGroups as $group) {
            $teacherRows = $group['teacher_rows'];
            $rowspan = count($teacherRows);

            foreach ($teacherRows as $index => $teacherRow) {
                $teacherId = (int) $teacherRow['id'];
                $teacherIds[] = $teacherId;
                $rowspansByTeacherId[$teacherId] = $index === 0 ? $rowspan : 0;
            }
        }

        $paginator = new LengthAwarePaginator(
            $pagedGroups,
            $groupCount,
            $groupsPerPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );

        return [
            'teacher_ids' => $teacherIds,
            'rowspans_by_teacher_id' => $rowspansByTeacherId,
            'group_count' => $groupCount,
            'paginator' => $paginator,
        ];
    }
}
