<?php

namespace App\Support;

use App\Models\AccountInformation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Coach Team — 기관 단위 기관지원/교사지원 커버리지.
 *
 * 지원 완료·유형·기간 규칙은 {@see CoachTeamSupportMatrixAggregator} 와 동일하다.
 * 대면·전화·화상·교사지원 건수는 Coach Team 작성자(TR_Name 등) 건만 집계한다.
 *
 * @phpstan-type CoverageRow array{
 *     sk_code: string,
 *     institution: string,
 *     coach: string,
 *     visit_count: int,
 *     phone_count: int,
 *     video_count: int,
 *     institution_total_count: int,
 *     teacher_count: int,
 *     has_institution_support: bool,
 *     has_teacher_support: bool,
 *     is_fully_unsupported: bool,
 * }
 * @phpstan-type CoverageCounts array{
 *     total: int,
 *     inst_supported: int,
 *     inst_unsupported: int,
 *     teacher_supported: int,
 *     teacher_unsupported: int,
 *     fully_unsupported: int,
 * }
 */
final class CoachTeamInstitutionCoverageAggregator
{
    public const FILTER_ALL = '';

    public const FILTER_INST_SUPPORTED = 'inst_supported';

    public const FILTER_INST_UNSUPPORTED = 'inst_unsupported';

    /**
     * @return array<string, string>
     */
    public static function coverageFilterLabels(): array
    {
        return [
            self::FILTER_INST_SUPPORTED => '기관지원됨',
            self::FILTER_INST_UNSUPPORTED => '기관미지원',
        ];
    }

    /**
     * @return list<string>
     */
    public static function coverageFilterKeys(): array
    {
        return array_keys(self::coverageFilterLabels());
    }

    /**
     * 표·엑셀용 건수 표시.
     */
    public static function formatCount(int $count): string
    {
        return (string) $count;
    }

    /**
     * 표 컬럼 키 → matrix row_key / 한글 라벨.
     *
     * @return array<string, array{row_key: string, label: string}>
     */
    public static function supportTypeColumns(): array
    {
        return [
            'visit' => ['row_key' => 'inst_visit', 'label' => '대면'],
            'phone' => ['row_key' => 'inst_phone', 'label' => '전화'],
            'video' => ['row_key' => 'inst_video', 'label' => '화상'],
        ];
    }

    /**
     * 기관·유형별 완료 지원 상세 (건수 클릭용).
     * 권한 없거나 0건이면 null.
     *
     * @return array{
     *     sk_code: string,
     *     institution: string,
     *     type_key: string,
     *     type_label: string,
     *     rows: list<array{
     *         id: int|null,
     *         date: string,
     *         coach: string,
     *         type: string,
     *         status: string,
     *         detail_key: string,
     *     }>
     * }|null
     */
    public static function typeDetail(
        string $skCode,
        string $typeKey,
        ?int $year,
        User $viewer,
    ): ?array {
        $columns = self::supportTypeColumns();
        if (! isset($columns[$typeKey])) {
            return null;
        }

        $normalizedSk = SkCodeNormalizer::normalize($skCode) ?? '';
        if ($normalizedSk === '') {
            return null;
        }

        $visible = self::masterInstitutions($viewer, '')
            ->first(fn (array $row): bool => $row['sk_code'] === $normalizedSk);
        if ($visible === null) {
            return null;
        }

        $rowKey = $columns[$typeKey]['row_key'];
        $details = [];
        [, $aliasToPrimary] = CoachTeamSupportMatrixAggregator::coachTeamMemberMaps();

        foreach (CoachTeamSupportMatrixAggregator::events($year) as $event) {
            if (! self::eventAuthorIsCoachTeamMember($event, $aliasToPrimary)) {
                continue;
            }

            $eventSk = SkCodeNormalizer::normalize((string) ($event['sk_code'] ?? '')) ?? '';
            if ($eventSk !== $normalizedSk) {
                continue;
            }
            if ((string) ($event['row_key'] ?? '') !== $rowKey) {
                continue;
            }

            $detailKey = (string) ($event['detail_key'] ?? '');
            $id = null;
            if (preg_match('/^account:(\d+)$/', $detailKey, $match) === 1) {
                $id = (int) $match[1];
            }

            $details[] = [
                'id' => $id,
                'date' => (string) ($event['date'] ?? ''),
                'coach' => (string) ($event['coach'] ?? ''),
                'type' => (string) ($event['type_label'] ?? $columns[$typeKey]['label']),
                'status' => (string) ($event['status'] ?? ''),
                'detail_key' => $detailKey,
            ];
        }

        if ($details === []) {
            return null;
        }

        usort($details, fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return [
            'sk_code' => $normalizedSk,
            'institution' => (string) $visible['institution'],
            'type_key' => $typeKey,
            'type_label' => $columns[$typeKey]['label'],
            'rows' => $details,
        ];
    }

    /**
     * @return Collection<int, CoverageRow>
     */
    public static function rows(
        ?int $year,
        User $viewer,
        string $search = '',
        string $filterCoach = '',
        string $coverageFilter = '',
    ): Collection {
        return self::buildRows($year, $viewer, $search, $filterCoach)
            ->filter(fn (array $row): bool => self::matchesCoverageFilter($row, $coverageFilter))
            ->values();
    }

    /**
     * 필터(검색·Coach) 적용 후, 커버리지 토글용 건수.
     * coverageFilter 는 카운트에 반영하지 않는다.
     *
     * @return CoverageCounts
     */
    public static function counts(
        ?int $year,
        User $viewer,
        string $search = '',
        string $filterCoach = '',
    ): array {
        $rows = self::buildRows($year, $viewer, $search, $filterCoach);

        $counts = [
            'total' => $rows->count(),
            'inst_supported' => 0,
            'inst_unsupported' => 0,
            'teacher_supported' => 0,
            'teacher_unsupported' => 0,
            'fully_unsupported' => 0,
        ];

        foreach ($rows as $row) {
            if ($row['has_institution_support']) {
                $counts['inst_supported']++;
            } else {
                $counts['inst_unsupported']++;
            }

            if ($row['has_teacher_support']) {
                $counts['teacher_supported']++;
            } else {
                $counts['teacher_unsupported']++;
            }

            if ($row['is_fully_unsupported']) {
                $counts['fully_unsupported']++;
            }
        }

        return $counts;
    }

    /**
     * @return Collection<int, CoverageRow>
     */
    private static function buildRows(
        ?int $year,
        User $viewer,
        string $search,
        string $filterCoach,
    ): Collection {
        $institutions = self::masterInstitutions($viewer, $filterCoach);
        if ($institutions->isEmpty()) {
            return collect();
        }

        $supportBySk = self::supportIndexBySk($year);
        $searchNeedle = ManagerNameNormalizer::normalize(trim($search));

        $rows = [];
        foreach ($institutions as $institution) {
            $skCode = (string) $institution['sk_code'];
            $support = $supportBySk[$skCode] ?? self::emptySupportBucket();

            $visitCount = (int) $support['visit_count'];
            $phoneCount = (int) $support['phone_count'];
            $videoCount = (int) $support['video_count'];

            $row = [
                'sk_code' => $skCode,
                'institution' => (string) $institution['institution'],
                'coach' => (string) $institution['coach'],
                'visit_count' => $visitCount,
                'phone_count' => $phoneCount,
                'video_count' => $videoCount,
                'institution_total_count' => $visitCount + $phoneCount + $videoCount,
                'teacher_count' => $support['teacher_count'],
                'has_institution_support' => $support['has_institution_support'],
                'has_teacher_support' => $support['has_teacher_support'],
                'is_fully_unsupported' => ! $support['has_institution_support'] && ! $support['has_teacher_support'],
            ];

            if ($searchNeedle !== '' && ! self::matchesSearch($row, $searchNeedle)) {
                continue;
            }

            $rows[] = $row;
        }

        return collect($rows)
            ->sortBy([
                ['coach', 'asc'],
                ['institution', 'asc'],
            ], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @param  CoverageRow  $row
     */
    private static function matchesCoverageFilter(array $row, string $coverageFilter): bool
    {
        return match ($coverageFilter) {
            self::FILTER_INST_SUPPORTED => $row['has_institution_support'],
            self::FILTER_INST_UNSUPPORTED => ! $row['has_institution_support'],
            default => true,
        };
    }

    /**
     * @param  CoverageRow  $row
     */
    private static function matchesSearch(array $row, string $needle): bool
    {
        $haystacks = [
            ManagerNameNormalizer::normalize($row['institution']),
            ManagerNameNormalizer::normalize($row['coach']),
            ManagerNameNormalizer::normalize($row['sk_code']),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, array{sk_code: string, institution: string, coach: string}>
     */
    private static function masterInstitutions(User $viewer, string $filterCoach): Collection
    {
        if (! Schema::hasTable('S_Account_Information')) {
            return collect();
        }

        [$displayByPrimary, $aliasToPrimary] = CoachTeamSupportMatrixAggregator::coachTeamMemberMaps();
        if ($displayByPrimary === []) {
            return collect();
        }

        $allowedPrimaryKeys = array_keys($displayByPrimary);
        $normalizedFilterCoach = ManagerNameNormalizer::normalize(trim($filterCoach));
        if ($normalizedFilterCoach !== '') {
            $filterPrimary = $aliasToPrimary[$normalizedFilterCoach] ?? null;
            if ($filterPrimary === null) {
                return collect();
            }
            $allowedPrimaryKeys = [$filterPrimary];
        } elseif (! self::viewerSeesAllCoachInstitutions($viewer)) {
            $viewerPrimaries = [];
            foreach (CoachTeacherScope::resolveTrAliases($viewer) as $alias) {
                $primary = $aliasToPrimary[$alias] ?? null;
                if ($primary !== null) {
                    $viewerPrimaries[$primary] = true;
                }
            }
            $allowedPrimaryKeys = array_keys($viewerPrimaries);
            if ($allowedPrimaryKeys === []) {
                return collect();
            }
        }

        $hidden = CoachTeacherScope::hiddenInstitutionSkCodes();
        $query = AccountInformation::query()
            ->whereNotNull('TR')
            ->where('TR', '!=', '')
            ->whereNotNull('SK_Code')
            ->where('SK_Code', '!=', '');

        if ($hidden !== []) {
            $query->whereNotIn('SK_Code', $hidden);
        }

        $allowedLookup = array_fill_keys($allowedPrimaryKeys, true);
        $rows = [];

        foreach ($query->get(['SK_Code', 'Account_Name', 'TR']) as $account) {
            $skCode = SkCodeNormalizer::normalize((string) $account->SK_Code);
            if ($skCode === null) {
                continue;
            }

            $trKey = ManagerNameNormalizer::normalize((string) $account->TR);
            $primaryKey = $aliasToPrimary[$trKey] ?? null;
            if ($primaryKey === null || ! isset($allowedLookup[$primaryKey])) {
                continue;
            }

            $rows[$skCode] = [
                'sk_code' => $skCode,
                'institution' => trim((string) ($account->Account_Name ?? '')) ?: $skCode,
                'coach' => $displayByPrimary[$primaryKey] ?? trim((string) $account->TR),
            ];
        }

        return collect(array_values($rows));
    }

    private static function viewerSeesAllCoachInstitutions(User $viewer): bool
    {
        return TeamMenuContext::hasExpandedReadScope($viewer)
            || TeamMenuContext::hasAdminMenuDataScope($viewer)
            || $viewer->canViewCoachTeamKpi();
    }

    /**
     * @return array<string, array{
     *     visit_count: int,
     *     phone_count: int,
     *     video_count: int,
     *     teacher_count: int,
     *     has_institution_support: bool,
     *     has_teacher_support: bool,
     * }>
     */
    private static function supportIndexBySk(?int $year): array
    {
        /** @var array<string, array{visit_count: int, phone_count: int, video_count: int, teacher_count: int, has_institution_support: bool, has_teacher_support: bool}> $index */
        $index = [];
        [, $aliasToPrimary] = CoachTeamSupportMatrixAggregator::coachTeamMemberMaps();

        foreach (CoachTeamSupportMatrixAggregator::events($year) as $event) {
            if (! self::eventAuthorIsCoachTeamMember($event, $aliasToPrimary)) {
                continue;
            }

            $skCode = SkCodeNormalizer::normalize((string) ($event['sk_code'] ?? '')) ?? '';
            if ($skCode === '') {
                continue;
            }

            if (! isset($index[$skCode])) {
                $index[$skCode] = self::emptySupportBucket();
            }

            $rowKey = (string) ($event['row_key'] ?? '');
            $group = CoachTeamSupportMatrixAggregator::matrixRowGroupMap()[$rowKey] ?? '';

            if ($group === 'institution') {
                $index[$skCode]['has_institution_support'] = true;
                $field = match ($rowKey) {
                    'inst_visit' => 'visit_count',
                    'inst_phone' => 'phone_count',
                    'inst_video' => 'video_count',
                    default => null,
                };
                if ($field !== null) {
                    $index[$skCode][$field]++;
                }
            } elseif ($group === 'teacher') {
                $index[$skCode]['has_teacher_support'] = true;
                $index[$skCode]['teacher_count']++;
            }
        }

        return $index;
    }

    /**
     * KPI 매트릭스와 동일: 작성자(TR_Name 등)가 Coach Team employee 에 매핑될 때만 집계.
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, string>  $aliasToPrimary
     */
    private static function eventAuthorIsCoachTeamMember(array $event, array $aliasToPrimary): bool
    {
        $coachKey = ManagerNameNormalizer::normalize((string) ($event['coach'] ?? ''));

        return $coachKey !== '' && isset($aliasToPrimary[$coachKey]);
    }

    /**
     * @return array{
     *     visit_count: 0,
     *     phone_count: 0,
     *     video_count: 0,
     *     teacher_count: 0,
     *     has_institution_support: false,
     *     has_teacher_support: false,
     * }
     */
    private static function emptySupportBucket(): array
    {
        return [
            'visit_count' => 0,
            'phone_count' => 0,
            'video_count' => 0,
            'teacher_count' => 0,
            'has_institution_support' => false,
            'has_teacher_support' => false,
        ];
    }
}
