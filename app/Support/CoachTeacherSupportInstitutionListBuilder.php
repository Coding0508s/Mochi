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
     *         id: int,
     *         name: string,
     *         section: 'supported'|'unsupported',
     *         latest_support_at: string|null,
     *         latest_support_ts: int
     *     }>
     * }
     */
    public function build(Builder $baseQuery, ?int $year, int $page, int $groupsPerPage = 50): array
    {
        $supportCondition = TeacherSupportListActivity::supportHistorySqlCondition($year);
        $latestSupportExpression = TeacherSupportListActivity::latestSupportDateSqlExpression($year);

        /** @var Collection<int, object{ID: int|string, Name: string|null, has_support: int|string|null, latest_support_at: string|null}> $rows */
        $rows = (clone $baseQuery)
            ->select([
                'Teachers.ID',
                'Teachers.Name',
            ])
            ->selectRaw("CASE WHEN {$supportCondition} THEN 1 ELSE 0 END AS has_support")
            ->selectRaw("CASE WHEN {$supportCondition} THEN {$latestSupportExpression} ELSE NULL END AS latest_support_at")
            ->get();

        /** @var list<array{id: int, name: string, section: 'supported'|'unsupported', latest_support_at: string|null, latest_support_ts: int}> $teacherRows */
        $teacherRows = [];

        foreach ($rows as $row) {
            $teacherId = (int) $row->ID;
            $section = (int) ($row->has_support ?? 0) === 1 ? 'supported' : 'unsupported';
            $latestSupportAt = $row->latest_support_at !== null ? (string) $row->latest_support_at : null;
            $teacherRows[] = [
                'id' => $teacherId,
                'name' => trim((string) ($row->Name ?? '')),
                'section' => $section,
                'latest_support_at' => $latestSupportAt,
                'latest_support_ts' => $latestSupportAt !== null ? (strtotime($latestSupportAt) ?: 0) : 0,
            ];
        }

        usort($teacherRows, function (array $left, array $right): int {
            if ($left['section'] !== $right['section']) {
                return $left['section'] === 'supported' ? -1 : 1;
            }

            if ($left['section'] === 'supported') {
                if ($left['latest_support_ts'] !== $right['latest_support_ts']) {
                    return $right['latest_support_ts'] <=> $left['latest_support_ts'];
                }
            } else {
                if ($left['latest_support_ts'] !== $right['latest_support_ts']) {
                    return $right['latest_support_ts'] <=> $left['latest_support_ts'];
                }
            }
            if ($left['name'] !== $right['name']) {
                return strcmp($left['name'], $right['name']);
            }

            return $left['id'] <=> $right['id'];
        });

        $groupCollection = collect($teacherRows);
        $groupCount = count($teacherRows);

        $page = max(1, $page);
        $groupsPerPage = max(1, $groupsPerPage);
        $pagedGroups = $groupCollection->forPage($page, $groupsPerPage)->values();

        /** @var list<int> $teacherIds */
        $teacherIds = $pagedGroups->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
        /** @var array<int, int> $rowspansByTeacherId */
        $rowspansByTeacherId = $pagedGroups
            ->pluck('id')
            ->mapWithKeys(fn (mixed $id): array => [(int) $id => 1])
            ->all();

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
