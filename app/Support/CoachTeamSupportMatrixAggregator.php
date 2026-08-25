<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\SupportRecord;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coach Team KPI — 지원 유형 × 기간 건수 매트릭스 집계.
 *
 * 특정 연도: 업무 연도 Y-01-01~(Y+1)-03-31, 열 키 "Y-m".
 * 「전체」: 달력 1~12월 합산, 열 키 int 1~12 (spillover 없음).
 *
 * @phpstan-type PeriodKey int|string
 * @phpstan-type MatrixPeriodCounts array<PeriodKey, int>
 * @phpstan-type MatrixRowCounts array<string, MatrixPeriodCounts>
 * @phpstan-type PeriodColumn array{key: PeriodKey, year: int, month: int, label: string, is_spillover: bool}
 * @phpstan-type CoachMatrixRow array{
 *     coach: string,
 *     total: int,
 *     institution_total: int,
 *     teacher_total: int,
 *     months: MatrixPeriodCounts,
 *     rows: MatrixRowCounts,
 * }
 * @phpstan-type DetailItem array{
 *     detail_key: string,
 *     date: string,
 *     month: int,
 *     period_key: PeriodKey,
 *     coach: string,
 *     row_key: string,
 *     type_label: string,
 *     subject: string,
 *     institution: string,
 *     status: string,
 *     source: string,
 * }
 */
final class CoachTeamSupportMatrixAggregator
{
    /**
     * @param  ?int  $year  null 이면 최근 N년(전체)
     * @return Collection<int, CoachMatrixRow>
     */
    public static function byCoach(?int $year, string $searchCoach = ''): Collection
    {
        [$displayByPrimary, $aliasToPrimary] = self::coachTeamMemberMaps();
        $rowKeys = self::matrixRowKeys();

        /** @var array<string, CoachMatrixRow> $byCoach */
        $byCoach = [];
        foreach ($displayByPrimary as $primaryKey => $displayName) {
            $emptyRows = [];
            foreach ($rowKeys as $key) {
                $emptyRows[$key] = self::emptyPeriodCounts($year);
            }

            $byCoach[$primaryKey] = [
                'coach' => $displayName,
                'total' => 0,
                'institution_total' => 0,
                'teacher_total' => 0,
                'months' => self::emptyPeriodCounts($year),
                'rows' => $emptyRows,
            ];
        }

        if ($byCoach === []) {
            return collect();
        }

        $rowGroups = self::matrixRowGroupMap();

        foreach (self::collectEvents($year) as $event) {
            $coachKey = ManagerNameNormalizer::normalize((string) $event['coach']);
            $primaryKey = $aliasToPrimary[$coachKey] ?? null;
            if ($primaryKey === null || ! isset($byCoach[$primaryKey])) {
                continue;
            }

            $periodKey = $event['period_key'];
            $rowKey = $event['row_key'];
            if (! array_key_exists($periodKey, $byCoach[$primaryKey]['months'])) {
                continue;
            }

            $byCoach[$primaryKey]['total']++;
            $byCoach[$primaryKey]['months'][$periodKey]++;
            $byCoach[$primaryKey]['rows'][$rowKey][$periodKey]++;

            $group = $rowGroups[$rowKey] ?? '';
            if ($group === 'institution') {
                $byCoach[$primaryKey]['institution_total']++;
            } elseif ($group === 'teacher') {
                $byCoach[$primaryKey]['teacher_total']++;
            }
        }

        $rows = collect(array_values($byCoach))
            ->sortBy('coach', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $search = trim($searchCoach);
        if ($search !== '') {
            $needle = ManagerNameNormalizer::normalize($search);
            $rows = $rows
                ->filter(fn (array $row): bool => str_contains(
                    ManagerNameNormalizer::normalize($row['coach']),
                    $needle,
                ))
                ->values();
        }

        return $rows;
    }

    /**
     * SQL 연도 후보. 특정 연도는 업무 연도 spillover 때문에 Y와 Y+1을 함께 조회한다.
     *
     * @return list<int>
     */
    public static function yearsForFilter(?int $year): array
    {
        if ($year !== null) {
            return [$year, $year + 1];
        }

        $lookback = max(0, (int) config('coach_team_kpi.all_years_lookback', 3));
        $end = (int) now()->year;
        $years = [];
        for ($y = $end; $y >= $end - $lookback; $y--) {
            $years[] = $y;
        }

        return $years;
    }

    public static function yearRangeLabel(?int $year): string
    {
        if ($year !== null) {
            return $year.'년';
        }

        $years = self::yearsForFilter(null);

        return min($years).'~'.max($years).'년';
    }

    /**
     * @return list<PeriodColumn>
     */
    public static function periodColumns(?int $year): array
    {
        if ($year === null) {
            $columns = [];
            for ($month = 1; $month <= 12; $month++) {
                $columns[] = [
                    'key' => $month,
                    'year' => 0,
                    'month' => $month,
                    'label' => $month.'월',
                    'is_spillover' => false,
                ];
            }

            return $columns;
        }

        $columns = [];
        for ($month = 1; $month <= 12; $month++) {
            $columns[] = [
                'key' => self::formatYearMonthKey($year, $month),
                'year' => $year,
                'month' => $month,
                'label' => $month.'월',
                'is_spillover' => false,
            ];
        }

        $nextYear = $year + 1;
        foreach (self::spilloverMonths() as $month) {
            $columns[] = [
                'key' => self::formatYearMonthKey($nextYear, $month),
                'year' => $nextYear,
                'month' => $month,
                'label' => ($nextYear % 100).'년 '.$month.'월',
                'is_spillover' => true,
            ];
        }

        return $columns;
    }

    /**
     * @return MatrixPeriodCounts
     */
    public static function emptyPeriodCounts(?int $year): array
    {
        $counts = [];
        foreach (self::periodColumns($year) as $column) {
            $counts[$column['key']] = 0;
        }

        return $counts;
    }

    public static function periodKeyFromDate(string $ymd, ?int $filterYear): int|string|null
    {
        try {
            $date = Carbon::parse($ymd)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if (! self::dateIsInScope($date, $filterYear)) {
            return null;
        }

        if ($filterYear === null) {
            return (int) $date->month;
        }

        return self::formatYearMonthKey((int) $date->year, (int) $date->month);
    }

    public static function periodLabel(?int $filterYear, int|string|null $periodKey): string
    {
        if ($periodKey === null || $periodKey === '') {
            return '';
        }

        foreach (self::periodColumns($filterYear) as $column) {
            if ((string) $column['key'] === (string) $periodKey) {
                if ($column['is_spillover'] || $filterYear === null) {
                    return $column['label'];
                }

                return $filterYear.'년 '.$column['month'].'월';
            }
        }

        return (string) $periodKey;
    }

    /**
     * @return list<int>
     */
    public static function spilloverMonths(): array
    {
        $months = config('coach_team_kpi.spillover_months', [1, 2, 3]);
        if (! is_array($months)) {
            return [1, 2, 3];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $month): int => (int) $month, $months),
            static fn (int $month): bool => $month >= 1 && $month <= 12,
        ));
    }

    public static function formatYearMonthKey(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    public static function dateIsInScope(Carbon $date, ?int $filterYear): bool
    {
        if ($filterYear === null) {
            $years = self::yearsForFilter(null);

            return in_array((int) $date->year, $years, true);
        }

        $start = Carbon::create($filterYear, 1, 1)->startOfDay();
        $spillover = self::spilloverMonths();
        $lastMonth = $spillover !== [] ? max($spillover) : 3;
        $end = Carbon::create($filterYear + 1, $lastMonth, 1)->endOfMonth()->startOfDay();

        return $date->betweenIncluded($start, $end);
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>}
     *                                                                   displayByPrimary, aliasToPrimary
     */
    public static function coachTeamMemberMaps(): array
    {
        if (! Schema::hasTable('employee')) {
            return [[], []];
        }

        $depts = config('coach_team_kpi.coach_work_depts', []);
        if (! is_array($depts) || $depts === []) {
            return [[], []];
        }

        $normalizedDepts = collect($depts)
            ->map(fn (mixed $dept): string => mb_strtoupper(trim((string) $dept)))
            ->filter(fn (string $dept): bool => $dept !== '')
            ->unique()
            ->values()
            ->all();

        if ($normalizedDepts === []) {
            return [[], []];
        }

        $displayByPrimary = [];
        $aliasToPrimary = [];

        $employees = Employee::query()
            ->whereIn('WORKDEPT', $normalizedDepts)
            ->where('STATUS', 1)
            ->get(['ENGLISHNAME', 'KOREANAME']);

        foreach ($employees as $employee) {
            $english = trim((string) ($employee->ENGLISHNAME ?? ''));
            $korean = trim((string) ($employee->KOREANAME ?? ''));
            $displayName = $english !== '' ? $english : $korean;
            if ($displayName === '') {
                continue;
            }

            $primaryKey = ManagerNameNormalizer::normalize($displayName);
            if ($primaryKey === '') {
                continue;
            }

            $displayByPrimary[$primaryKey] = $displayName;

            foreach ([$english, $korean, $displayName] as $alias) {
                $aliasKey = ManagerNameNormalizer::normalize($alias);
                if ($aliasKey === '') {
                    continue;
                }
                $aliasToPrimary[$aliasKey] = $primaryKey;
            }
        }

        return [$displayByPrimary, $aliasToPrimary];
    }

    /**
     * @return list<DetailItem>
     */
    public static function detailItems(
        ?int $year,
        string $coach,
        string $rowKey,
        int|string|null $periodKey = null,
    ): array {
        $normalizedCoach = ManagerNameNormalizer::normalize($coach);
        [, $aliasToPrimary] = self::coachTeamMemberMaps();
        $primaryKey = $aliasToPrimary[$normalizedCoach] ?? null;
        if ($primaryKey === null || ! in_array($rowKey, self::matrixRowKeys(), true)) {
            return [];
        }

        $validPeriodKeys = array_map(
            static fn (array $column): string => (string) $column['key'],
            self::periodColumns($year),
        );
        $normalizedPeriodKey = $periodKey === null || $periodKey === ''
            ? null
            : (string) $periodKey;
        if ($normalizedPeriodKey !== null && ! in_array($normalizedPeriodKey, $validPeriodKeys, true)) {
            return [];
        }

        $items = [];
        foreach (self::collectEvents($year) as $event) {
            $eventPrimary = $aliasToPrimary[ManagerNameNormalizer::normalize($event['coach'])] ?? null;
            if ($eventPrimary !== $primaryKey) {
                continue;
            }
            if ($event['row_key'] !== $rowKey) {
                continue;
            }
            if ($normalizedPeriodKey !== null && (string) $event['period_key'] !== $normalizedPeriodKey) {
                continue;
            }

            $items[] = [
                'detail_key' => $event['detail_key'],
                'date' => $event['date'],
                'month' => $event['month'],
                'period_key' => $event['period_key'],
                'coach' => $event['coach'],
                'row_key' => $event['row_key'],
                'type_label' => $event['type_label'],
                'subject' => $event['subject'],
                'institution' => $event['institution'],
                'status' => $event['status'],
                'source' => $event['source'],
            ];
        }

        usort($items, function (array $left, array $right): int {
            return strcmp($right['date'], $left['date'])
                ?: strcmp($left['detail_key'], $right['detail_key']);
        });

        return $items;
    }

    /**
     * 엑셀 내보내기용 — Coach Team 소속 지원 내역(기관+교사).
     *
     * @return list<array{
     *     coach: string,
     *     group: string,
     *     group_label: string,
     *     row_label: string,
     *     type_label: string,
     *     date: string,
     *     month: int|string,
     *     subject: string,
     *     institution: string,
     *     status: string,
     * }>
     */
    public static function exportDetailItems(?int $year, string $searchCoach = ''): array
    {
        [$displayByPrimary, $aliasToPrimary] = self::coachTeamMemberMaps();
        if ($displayByPrimary === []) {
            return [];
        }

        $rowMeta = [];
        foreach (config('coach_team_kpi.matrix_rows', []) as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $rowMeta[$key] = [
                'group' => (string) ($row['group'] ?? ''),
                'label' => (string) ($row['label'] ?? $key),
            ];
        }

        $searchNeedle = ManagerNameNormalizer::normalize(trim($searchCoach));
        $items = [];

        foreach (self::collectEvents($year) as $event) {
            $primaryKey = $aliasToPrimary[ManagerNameNormalizer::normalize((string) $event['coach'])] ?? null;
            if ($primaryKey === null) {
                continue;
            }

            $coachDisplay = $displayByPrimary[$primaryKey] ?? (string) $event['coach'];
            if ($searchNeedle !== '' && ! str_contains(ManagerNameNormalizer::normalize($coachDisplay), $searchNeedle)) {
                continue;
            }

            $meta = $rowMeta[$event['row_key']] ?? null;
            if ($meta === null) {
                continue;
            }

            $group = $meta['group'];
            $items[] = [
                'coach' => $coachDisplay,
                'group' => $group,
                'group_label' => $group === 'institution' ? '기관지원' : '교사지원',
                'row_label' => $meta['label'],
                'type_label' => (string) $event['type_label'],
                'date' => (string) $event['date'],
                'month' => $year === null
                    ? (int) $event['month']
                    : (string) $event['period_key'],
                'subject' => (string) $event['subject'],
                'institution' => (string) $event['institution'],
                'status' => (string) $event['status'],
            ];
        }

        usort($items, function (array $left, array $right): int {
            return strcmp($left['coach'], $right['coach'])
                ?: strcmp($left['group_label'], $right['group_label'])
                ?: strcmp($right['date'], $left['date'])
                ?: strcmp($left['type_label'], $right['type_label']);
        });

        return $items;
    }

    /**
     * @return list<array{key: string, group: string, label: string}>
     */
    public static function matrixRowDefinitions(): array
    {
        return array_map(
            fn (array $row): array => [
                'key' => (string) $row['key'],
                'group' => (string) $row['group'],
                'label' => (string) $row['label'],
            ],
            config('coach_team_kpi.matrix_rows', []),
        );
    }

    /**
     * @return array<string, string> row_key => group
     */
    public static function matrixRowGroupMap(): array
    {
        $map = [];
        foreach (self::matrixRowDefinitions() as $row) {
            $map[$row['key']] = $row['group'];
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function matrixRowKeys(): array
    {
        return array_column(self::matrixRowDefinitions(), 'key');
    }

    /**
     * @return MatrixPeriodCounts
     */
    public static function emptyMonthCounts(): array
    {
        return self::emptyPeriodCounts(null);
    }

    /**
     * 기관 커버리지 등 외부 집계용 이벤트(완료·기간 스코프 동일).
     *
     * @return list<array{
     *     coach: string,
     *     row_key: string,
     *     month: int,
     *     period_key: PeriodKey,
     *     date: string,
     *     type_label: string,
     *     subject: string,
     *     institution: string,
     *     sk_code: string,
     *     status: string,
     *     source: string,
     *     detail_key: string,
     * }>
     */
    public static function events(?int $year): array
    {
        return self::collectEvents($year);
    }

    /**
     * @return list<array{
     *     coach: string,
     *     row_key: string,
     *     month: int,
     *     period_key: PeriodKey,
     *     date: string,
     *     type_label: string,
     *     subject: string,
     *     institution: string,
     *     sk_code: string,
     *     status: string,
     *     source: string,
     *     detail_key: string,
     * }>
     */
    private static function collectEvents(?int $year): array
    {
        $mochi = self::mochiTeacherEvents($year);
        $mochiKeys = [];
        foreach ($mochi as $event) {
            $dedupeKey = self::teacherEventDedupeKey($event);
            if ($dedupeKey !== null) {
                $mochiKeys[$dedupeKey] = true;
            }
        }

        $legacy = [];
        foreach (self::legacyTeacherEvents($year) as $event) {
            $dedupeKey = self::teacherEventDedupeKey($event);
            if ($dedupeKey !== null && isset($mochiKeys[$dedupeKey])) {
                continue;
            }
            $legacy[] = $event;
        }

        return array_merge(
            self::institutionEvents($year),
            $mochi,
            $legacy,
        );
    }

    /**
     * @param  list<int>  $years
     */
    private static function sqlColumnMatchesYears(string $column, array $years): string
    {
        $parts = [];
        foreach ($years as $year) {
            $parts[] = ExcelSerialDate::sqlColumnInYear($column, $year);
        }

        if ($parts === []) {
            return '0 = 1';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return '('.implode(' OR ', $parts).')';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function teacherEventDedupeKey(array $event): ?string
    {
        $teacherId = (int) ($event['teacher_id'] ?? 0);
        $date = (string) ($event['date'] ?? '');
        $rowKey = (string) ($event['row_key'] ?? '');

        if ($teacherId <= 0 || $date === '' || $rowKey === '') {
            return null;
        }

        return $teacherId.'|'.$date.'|'.$rowKey;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function institutionEvents(?int $year): array
    {
        if (! Schema::hasTable('S_SupportInfo_Account')) {
            return [];
        }

        $years = self::yearsForFilter($year);
        $types = config('coach_team_kpi.institution_types', ['전화', '대면', '화상']);
        $query = SupportRecord::query()
            ->whereIn('Support_Type', $types)
            ->whereRaw(self::sqlColumnMatchesYears('Support_Date', $years));

        if (config('coach_team_kpi.institution_completed_only', true)) {
            $query->completed();
        }

        $events = [];
        foreach ($query->get(['ID', 'TR_Name', 'Support_Date', 'Support_Type', 'Account_Name', 'Target', 'Status', 'SK_Code']) as $record) {
            $date = ExcelSerialDate::toStorageString($record->Support_Date);
            if ($date === null) {
                continue;
            }

            $periodKey = self::periodKeyFromDate($date, $year);
            if ($periodKey === null) {
                continue;
            }

            $typeLabel = trim((string) $record->Support_Type);
            $rowKey = self::resolveRowKey($typeLabel, 'institution');
            if ($rowKey === null) {
                continue;
            }

            $coach = trim((string) ($record->TR_Name ?? ''));
            if ($coach === '') {
                continue;
            }

            $subject = trim((string) ($record->Target ?? ''));
            if ($subject === '') {
                $subject = trim((string) ($record->Account_Name ?? ''));
            }

            $events[] = [
                'coach' => $coach,
                'row_key' => $rowKey,
                'month' => (int) Carbon::parse($date)->month,
                'period_key' => $periodKey,
                'date' => $date,
                'type_label' => $typeLabel,
                'subject' => $subject !== '' ? $subject : '—',
                'institution' => trim((string) ($record->Account_Name ?? '')),
                'sk_code' => SkCodeNormalizer::normalize((string) ($record->SK_Code ?? '')) ?? '',
                'status' => trim((string) ($record->Status ?? '')),
                'source' => 'institution',
                'detail_key' => 'account:'.$record->ID,
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function mochiTeacherEvents(?int $year): array
    {
        $years = self::yearsForFilter($year);
        $typeLabels = config('coach_teacher_legacy_support.mochi_report_tables', []);
        $events = [];

        foreach (MochiTeacherSupportQuery::existingReportTables() as $table) {
            $conditions = [
                "{$table}.teacher_id IS NOT NULL",
                "{$table}.support_date IS NOT NULL",
                ExcelSerialDate::sqlDateValueIsNotBlank("{$table}.support_date"),
                self::sqlColumnMatchesYears("{$table}.support_date", $years),
            ];

            if (Schema::hasColumn($table, 'status')) {
                $conditions[] = "{$table}.status = '완료'";
            }

            $select = [
                "{$table}.id AS id",
                "{$table}.teacher_id AS teacher_id",
                "{$table}.support_date AS support_date",
            ];

            foreach (['coach_name', 'teacher_name', 'institution_name', 'sk_code', 'status'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $select[] = "{$table}.{$column} AS {$column}";
                }
            }

            $rows = DB::table($table)
                ->whereRaw(implode(' AND ', $conditions))
                ->get($select);

            $typeLabel = (string) ($typeLabels[$table] ?? '');
            $rowKey = self::resolveRowKey($typeLabel, 'teacher');
            if ($rowKey === null) {
                continue;
            }

            foreach ($rows as $row) {
                $date = ExcelSerialDate::toStorageString($row->support_date);
                if ($date === null) {
                    continue;
                }

                $periodKey = self::periodKeyFromDate($date, $year);
                if ($periodKey === null) {
                    continue;
                }

                $coach = trim((string) ($row->coach_name ?? ''));
                if ($coach === '') {
                    $coach = self::coachFromTeacherId((int) $row->teacher_id);
                }
                if ($coach === '') {
                    continue;
                }

                $skCode = SkCodeNormalizer::normalize((string) ($row->sk_code ?? '')) ?? '';
                if ($skCode === '') {
                    $skCode = self::skCodeFromTeacherId((int) $row->teacher_id);
                }

                $events[] = [
                    'coach' => $coach,
                    'row_key' => $rowKey,
                    'month' => (int) Carbon::parse($date)->month,
                    'period_key' => $periodKey,
                    'date' => $date,
                    'type_label' => $typeLabel,
                    'subject' => trim((string) ($row->teacher_name ?? '')) ?: '—',
                    'institution' => trim((string) ($row->institution_name ?? '')),
                    'sk_code' => $skCode,
                    'status' => trim((string) ($row->status ?? '완료')),
                    'source' => 'teacher',
                    'teacher_id' => (int) $row->teacher_id,
                    'detail_key' => 'mochi:'.$table.':'.$row->id,
                ];
            }
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function legacyTeacherEvents(?int $year): array
    {
        $years = self::yearsForFilter($year);
        $events = [];

        foreach (config('coach_teacher_legacy_support.legacy_sources', []) as $source) {
            $table = $source['table'] ?? null;
            if (! is_string($table) || $table === '' || ! Schema::hasTable($table)) {
                continue;
            }

            $teacherIdColumn = self::legacyTeacherIdColumn($table);
            if ($teacherIdColumn === null || ! Schema::hasColumn($table, 'SupportDate')) {
                continue;
            }

            $query = DB::table($table)
                ->whereNotNull('SupportDate')
                ->whereRaw(ExcelSerialDate::sqlDateValueIsNotBlank('SupportDate'))
                ->whereRaw(self::sqlColumnMatchesYears('SupportDate', $years));

            if (Schema::hasColumn($table, 'Status')) {
                $query->where('Status', '완료');
            }

            $select = ['ID', $teacherIdColumn, 'SupportDate'];
            foreach (['TR_Name', 'Teacher', 'SK_Code', 'Account_Name', 'Status', 'LVA_TYPE', 'ReportType'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $select[] = $column;
                }
            }

            foreach ($query->get($select) as $row) {
                $date = ExcelSerialDate::toStorageString($row->SupportDate);
                if ($date === null) {
                    continue;
                }

                $periodKey = self::periodKeyFromDate($date, $year);
                if ($periodKey === null) {
                    continue;
                }

                $typeLabel = self::legacyTypeLabel($source, $row);
                $rowKey = self::resolveRowKey($typeLabel, 'teacher');
                if ($rowKey === null) {
                    continue;
                }

                $coach = trim((string) ($row->TR_Name ?? ''));
                if ($coach === '') {
                    $coach = self::coachFromTeacherId((int) ($row->{$teacherIdColumn} ?? 0));
                }
                if ($coach === '') {
                    continue;
                }

                $teacherId = (int) ($row->{$teacherIdColumn} ?? 0);
                $skCode = SkCodeNormalizer::normalize((string) ($row->SK_Code ?? '')) ?? '';
                if ($skCode === '') {
                    $skCode = self::skCodeFromTeacherId($teacherId);
                }

                $events[] = [
                    'coach' => $coach,
                    'row_key' => $rowKey,
                    'month' => (int) Carbon::parse($date)->month,
                    'period_key' => $periodKey,
                    'date' => $date,
                    'type_label' => $typeLabel,
                    'subject' => trim((string) ($row->Teacher ?? '')) ?: '—',
                    'institution' => trim((string) ($row->Account_Name ?? '')),
                    'sk_code' => $skCode,
                    'status' => trim((string) ($row->Status ?? '완료')),
                    'source' => 'teacher',
                    'teacher_id' => $teacherId,
                    'detail_key' => 'legacy:'.$table.':'.$row->ID,
                ];
            }
        }

        return $events;
    }

    private static function resolveRowKey(string $typeLabel, string $group): ?string
    {
        $normalized = self::normalizeTypeLabel($typeLabel);
        if ($normalized === '') {
            return null;
        }

        foreach (config('coach_team_kpi.matrix_rows', []) as $row) {
            if (($row['group'] ?? '') !== $group) {
                continue;
            }

            $matchTypes = $row['match_types'] ?? [];
            if (config('coach_team_kpi.onsite_includes_ls', true)
                && ($row['key'] ?? '') === 'teacher_onsite') {
                $matchTypes = array_merge(
                    $matchTypes,
                    config('coach_team_kpi.ls_onsite_type_labels', []),
                );
            }

            foreach ($matchTypes as $matchType) {
                if (self::normalizeTypeLabel((string) $matchType) === $normalized) {
                    return (string) $row['key'];
                }
            }
        }

        return null;
    }

    private static function normalizeTypeLabel(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $normalized = preg_replace('/\s+/u', '', $lower);

        return is_string($normalized) ? $normalized : $lower;
    }

    private static function coachFromTeacherId(int $teacherId): string
    {
        if ($teacherId <= 0) {
            return '';
        }

        static $cache = [];
        if (array_key_exists($teacherId, $cache)) {
            return $cache[$teacherId];
        }

        $teacher = Teacher::query()
            ->with('institution.accountInfo')
            ->find($teacherId);

        $coach = trim((string) ($teacher?->institution?->accountInfo?->TR ?? ''));
        $cache[$teacherId] = $coach;

        return $coach;
    }

    private static function skCodeFromTeacherId(int $teacherId): string
    {
        if ($teacherId <= 0) {
            return '';
        }

        static $cache = [];
        if (array_key_exists($teacherId, $cache)) {
            return $cache[$teacherId];
        }

        $raw = Teacher::query()->whereKey($teacherId)->value('SK_Code');
        $skCode = SkCodeNormalizer::normalize(is_string($raw) ? $raw : null) ?? '';
        $cache[$teacherId] = $skCode;

        return $skCode;
    }

    private static function legacyTeacherIdColumn(string $table): ?string
    {
        foreach (['TeacherId', 'TeacherID', 'teacher_id'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private static function legacyTypeLabel(array $source, object $row): string
    {
        if (($source['type_resolver'] ?? null) === 'lva') {
            $lvaType = $row->LVA_TYPE ?? null;
            if (! filled($lvaType) && isset($row->ReportType)) {
                $lvaType = config('coach_teacher_legacy_support.lva_report_types')[(int) $row->ReportType] ?? null;
            }

            return filled($lvaType) ? '교사 지원 LVA '.$lvaType : '교사 지원 LVA';
        }

        return (string) ($source['type'] ?? '교사 지원');
    }
}
