<?php

namespace App\Support;

use App\Models\SupportRecord;
use Illuminate\Support\Collection;

/**
 * 기관 이슈를 기관+교사(또는 기관 공통) 단위로 묶는다.
 * 목록에는 그룹당 1행(대표=최신 이슈), 상세에서는 그룹 내 전체 이슈를 쓴다.
 */
class InstitutionIssueTeacherGrouper
{
    /**
     * @param  Collection<int, SupportRecord>  $records
     * @return list<array{
     *     group_key: string,
     *     sk_code: string,
     *     account_name: string,
     *     teacher_label: string,
     *     is_institution_common: bool,
     *     issue_count: int,
     *     urgent_count: int,
     *     latest: SupportRecord,
     *     issues: list<SupportRecord>
     * }>
     */
    public static function group(Collection $records): array
    {
        $sorted = $records->sort(function (SupportRecord $a, SupportRecord $b): int {
            $skCmp = strcmp(self::institutionSortKey($a), self::institutionSortKey($b));
            if ($skCmp !== 0) {
                return $skCmp;
            }

            $teacherCmp = strcmp(self::teacherSortKey($a), self::teacherSortKey($b));
            if ($teacherCmp !== 0) {
                return $teacherCmp;
            }

            $dateA = (string) ($a->Support_Date?->format('Y-m-d') ?? '');
            $dateB = (string) ($b->Support_Date?->format('Y-m-d') ?? '');
            $dateCmp = strcmp($dateB, $dateA);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            $timeA = (string) ($a->Meet_Time ?? '');
            $timeB = (string) ($b->Meet_Time ?? '');
            $timeCmp = strcmp($timeB, $timeA);
            if ($timeCmp !== 0) {
                return $timeCmp;
            }

            return ((int) $b->ID) <=> ((int) $a->ID);
        })->values();

        $grouped = $sorted->groupBy(fn (SupportRecord $record): string => self::groupKey($record));

        $rows = [];

        foreach ($grouped as $groupKey => $groupRecords) {
            /** @var Collection<int, SupportRecord> $groupRecords */
            $issues = $groupRecords->values()->all();
            $latest = $issues[0];
            $isCommon = ! filled($latest->Target);

            $rows[] = [
                'group_key' => (string) $groupKey,
                'sk_code' => (string) ($latest->SK_Code ?? ''),
                'account_name' => (string) ($latest->Account_Name ?? ''),
                'teacher_label' => $isCommon ? '기관 공통' : (string) $latest->Target,
                'is_institution_common' => $isCommon,
                'issue_count' => count($issues),
                'urgent_count' => $groupRecords->filter(
                    fn (SupportRecord $record): bool => (bool) ($record->is_urgent ?? false)
                )->count(),
                'latest' => $latest,
                'issues' => $issues,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $instCmp = strcmp($a['account_name'], $b['account_name']);
            if ($instCmp !== 0) {
                return $instCmp;
            }

            if ($a['is_institution_common'] !== $b['is_institution_common']) {
                return $a['is_institution_common'] ? -1 : 1;
            }

            return strcmp($a['teacher_label'], $b['teacher_label']);
        });

        return $rows;
    }

    /**
     * @param  list<array{sk_code: string}>  $groups
     * @return array<int, int>
     */
    public static function institutionRowspans(array $groups): array
    {
        $count = count($groups);
        $spans = array_fill(0, $count, 0);
        $i = 0;

        while ($i < $count) {
            $key = self::institutionKeyFromGroup($groups[$i]);
            $span = 1;
            while ($i + $span < $count
                && self::institutionKeyFromGroup($groups[$i + $span]) === $key) {
                $span++;
            }
            $spans[$i] = $span;
            $i += $span;
        }

        return $spans;
    }

    public static function groupKey(SupportRecord $record): string
    {
        return self::institutionKey($record).'|'.self::teacherKey($record);
    }

    public static function institutionKey(SupportRecord $record): string
    {
        $sk = trim((string) ($record->SK_Code ?? ''));

        return $sk !== '' ? 'sk:'.$sk : 'name:'.trim((string) ($record->Account_Name ?? ''));
    }

    public static function teacherKey(SupportRecord $record): string
    {
        $target = trim((string) ($record->Target ?? ''));

        return $target !== '' ? 't:'.$target : '__common__';
    }

    private static function institutionKeyFromGroup(array $group): string
    {
        $sk = trim((string) ($group['sk_code'] ?? ''));

        return $sk !== '' ? 'sk:'.$sk : 'name:'.trim((string) ($group['account_name'] ?? ''));
    }

    private static function institutionSortKey(SupportRecord $record): string
    {
        return self::institutionKey($record);
    }

    private static function teacherSortKey(SupportRecord $record): string
    {
        $target = trim((string) ($record->Target ?? ''));

        return $target === '' ? '0:' : '1:'.$target;
    }
}
