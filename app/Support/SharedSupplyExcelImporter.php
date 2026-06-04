<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\SharedSupply;
use App\Models\SharedSupplyItem;
use App\Models\SharedSupplyLabel;
use App\Models\TeamSchedule;
use App\Models\User;
use App\Models\VehicleUsageLog;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SharedSupplyExcelImporter
{
    private const VEHICLE_SCHEDULE_REF_PREFIX = '[excel-schedule:';

    private ?bool $hasLegacyItemNameColumn = null;

    private ?bool $hasLegacyLabelColumn = null;

    private ?bool $hasScheduleCategoryCodeColumn = null;

    private ?bool $hasEmployeeTable = null;

    private ?bool $canSyncVehicleUsageLogs = null;

    private ?bool $hasVehicleLogArrivalLocationColumn = null;

    /** @var array<string, SharedSupply> */
    private array $scheduleRefIndex = [];

    /** @var array<string, Collection<int, SharedSupply>> */
    private array $looseMatchIndex = [];

    /** @var array<int, VehicleUsageLog> */
    private array $vehicleLogsBySupplyId = [];

    private bool $matchIndexIsLoaded = false;

    /**
     * @return array{
     *   inserted: int,
     *   updated: int,
     *   deleted: int,
     *   skipped: int,
     *   errors: array<int, string>
     * }
     */
    public function importFromFile(string $filePath, int $actorUserId): array
    {
        set_time_limit(120);

        $spreadsheet = IOFactory::load($filePath);
        $headerMissingResult = null;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            $result = $this->importRows($rows, $actorUserId);

            if (! $this->isHeaderMissingResult($result)) {
                return $result;
            }

            $headerMissingResult = $result;
        }

        return $headerMissingResult ?? [
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'errors' => ['헤더(일자/시작시간/종료시간/사용자명 또는 작성자명/제목)를 찾지 못했습니다.'],
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{
     *   inserted: int,
     *   updated: int,
     *   deleted: int,
     *   skipped: int,
     *   errors: array<int, string>
     * }
     */
    public function importRows(array $rows, int $actorUserId): array
    {
        $headerIndex = $this->findHeaderRowIndex($rows);
        if ($headerIndex === null) {
            return [
                'inserted' => 0,
                'updated' => 0,
                'deleted' => 0,
                'skipped' => 0,
                'errors' => ['헤더(일자/시작시간/종료시간/사용자명 또는 작성자명/제목)를 찾지 못했습니다.'],
            ];
        }

        $headerMap = $this->buildHeaderMap($rows[$headerIndex] ?? []);
        $isVehicleLogFormat = array_key_exists('차량일정번호', $headerMap);
        $bannerSyncRange = $this->extractSyncDateRangeFromRows($rows, $headerIndex);
        $scannedDateRange = $this->scanImportDateRange($rows, $headerIndex, $headerMap, $isVehicleLogFormat);

        $this->initializeSchemaFlags();
        $this->resetMatchIndex();

        $preloadRange = $bannerSyncRange ?? $scannedDateRange;
        if ($preloadRange !== null) {
            $this->preloadMatchIndex($preloadRange['from'], $preloadRange['to']);
        }

        return DB::transaction(function () use ($rows, $actorUserId, $headerIndex, $headerMap, $isVehicleLogFormat, $bannerSyncRange): array {
            $importedSupplyIds = [];

            $result = SharedSupply::withoutEvents(function () use (
                $rows,
                $actorUserId,
                $headerIndex,
                $headerMap,
                $isVehicleLogFormat,
                $bannerSyncRange,
                &$importedSupplyIds,
            ): array {
                return $this->processImportRows(
                    rows: $rows,
                    actorUserId: $actorUserId,
                    headerIndex: $headerIndex,
                    headerMap: $headerMap,
                    isVehicleLogFormat: $isVehicleLogFormat,
                    bannerSyncRange: $bannerSyncRange,
                    importedSupplyIds: $importedSupplyIds,
                );
            });

            $this->syncCalendarForSupplies($importedSupplyIds);

            return $result;
        });
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int>  $headerMap
     * @param  array{from: Carbon, to: Carbon}|null  $bannerSyncRange
     * @param  array<int, int>  $importedSupplyIds
     * @return array{
     *   inserted: int,
     *   updated: int,
     *   deleted: int,
     *   skipped: int,
     *   errors: array<int, string>
     * }
     */
    private function processImportRows(
        array $rows,
        int $actorUserId,
        int $headerIndex,
        array $headerMap,
        bool $isVehicleLogFormat,
        ?array $bannerSyncRange,
        array &$importedSupplyIds,
    ): array {
        $usersQuery = User::query()->select(['id', 'name', 'employee_empno']);
        if ($this->hasEmployeeTable) {
            $usersQuery->with([
                'employee' => static fn ($query) => $query->select(['EMPNO', 'KOREANAME', 'ENGLISHNAME']),
            ]);
        }
        $users = $usersQuery->get();
        $items = SharedSupplyItem::query()->active()->get(['id', 'code', 'name', 'is_active', 'sort_order']);
        $labels = SharedSupplyLabel::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']);

        $defaultLabelId = (int) ($labels->firstWhere('code', '01')->id ?? $labels->first()->id ?? 0);
        $labelByCode = $labels->keyBy('code');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $importedRowDates = [];

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $excelRowNumber = $i + 1;
            $row = $rows[$i] ?? [];

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $vehicleScheduleNo = trim((string) $this->cellValue($row, $headerMap, '차량일정번호'));
            $derivedDate = $this->extractDateFromVehicleScheduleNo($vehicleScheduleNo);

            $dateValue = $this->cellValue($row, $headerMap, '일자');
            if ($isVehicleLogFormat && trim((string) $dateValue) === '' && $derivedDate !== null) {
                $dateValue = $derivedDate;
            }

            $startValue = $this->cellValue($row, $headerMap, '시작시간');
            $endValue = $this->cellValue($row, $headerMap, '종료시간');
            $itemValue = trim((string) $this->cellValue($row, $headerMap, '물품명'));
            $userNameValue = trim((string) $this->cellValue($row, $headerMap, '사용자명'));
            $titleValue = trim((string) $this->cellValue($row, $headerMap, '제목'));
            if ($isVehicleLogFormat && $titleValue === '') {
                $titleValue = '[출장 차량배차] 신청 및 예약';
            }

            $usagePurposeValue = trim((string) $this->cellValue($row, $headerMap, '사용목적명'));
            $arrivalLocationValue = trim((string) $this->cellValue($row, $headerMap, '도착위치'));
            $remarkValue = trim((string) $this->cellValue($row, $headerMap, '적요'));
            $purposeValue = $remarkValue;
            if ($isVehicleLogFormat) {
                $purposeValue = $this->mergePurposeForVehicleLog($usagePurposeValue, $remarkValue);
            }
            $odometerBefore = $this->parseIntegerValue($this->cellValue($row, $headerMap, '주행전계기판거리'));
            $odometerAfter = $this->parseIntegerValue($this->cellValue($row, $headerMap, '주행후계기판거리'));
            $distance = $this->parseIntegerValue($this->cellValue($row, $headerMap, '운행거리'));

            // 집계/메모 행 방지: 핵심 텍스트 컬럼이 비어 있으면 데이터 행으로 보지 않는다.
            if ($itemValue === '' && $userNameValue === '' && $titleValue === '') {
                continue;
            }

            $date = $this->parseDateValue($dateValue);
            if (! $date instanceof Carbon) {
                $errors[] = "{$excelRowNumber}행: 일자 형식을 읽을 수 없습니다.";
                $skipped++;

                continue;
            }

            if ($isVehicleLogFormat && trim((string) $startValue) === '' && trim((string) $endValue) === '') {
                $timeRange = $this->buildDefaultTimeRangeForVehicleLog($excelRowNumber);
                $startValue = $timeRange['start'];
                $endValue = $timeRange['end'];
            }

            $startParsed = $this->parseTime($startValue, false);
            $endParsed = $this->parseTime($endValue, true);
            if ($startParsed === null || $endParsed === null) {
                $errors[] = "{$excelRowNumber}행: 시간 형식은 09:00, 9:00, 24:00 형태만 허용됩니다.";
                $skipped++;

                continue;
            }

            $startsAt = $date->copy()->startOfDay()
                ->addDays($startParsed['dayOffset'])
                ->setTimeFromTimeString($startParsed['time']);
            $endsAt = $date->copy()->startOfDay()
                ->addDays($endParsed['dayOffset'])
                ->setTimeFromTimeString($endParsed['time']);

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                $errors[] = "{$excelRowNumber}행: 종료 시간이 시작 시간보다 늦어야 합니다.";
                $skipped++;

                continue;
            }

            $matchedUser = $this->matchUserByName($users->all(), $userNameValue);
            if ($matchedUser === null) {
                $matchedUser = $this->matchOrCreateUserByEmployeeMaster($userNameValue);
                if ($matchedUser instanceof User) {
                    $users->push($matchedUser);
                }
            }
            if ($matchedUser === null) {
                $errors[] = "{$excelRowNumber}행: 사용자명 '{$userNameValue}' 를 사용자 마스터에서 찾지 못했습니다.";
                $skipped++;

                continue;
            }

            $matchedItem = $this->resolveItemForImport($items, $itemValue, $titleValue);
            if ($matchedItem === null) {
                $errors[] = "{$excelRowNumber}행: 물품명 또는 제목 '{$itemValue}{$titleValue}' 에 해당하는 공용품코드를 찾지 못했습니다.";
                $skipped++;

                continue;
            }

            $labelCode = $this->resolveLabelCode($titleValue, $itemValue);
            $labelId = (int) ($labelByCode->get($labelCode)->id ?? $defaultLabelId);
            if ($labelId <= 0) {
                $errors[] = "{$excelRowNumber}행: 라벨 마스터를 찾을 수 없습니다.";
                $skipped++;

                continue;
            }

            $matchResult = $this->resolveSupplyMatch(
                isVehicleLogFormat: $isVehicleLogFormat,
                vehicleScheduleNo: $vehicleScheduleNo,
                userId: (int) $matchedUser->id,
                itemId: (int) $matchedItem->id,
                itemName: (string) $matchedItem->name,
                startsAt: $startsAt,
                title: $titleValue,
                usagePurpose: $usagePurposeValue,
                odometerBefore: $odometerBefore,
            );
            $existing = $matchResult['supply'];
            $ambiguousMatches = $matchResult['ambiguousCount'];

            if ($existing instanceof SharedSupply) {
                $updatePayload = [
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'shared_supply_label_id' => $labelId,
                    'title' => $titleValue,
                    'purpose' => $purposeValue !== '' ? $purposeValue : null,
                    'updated_by' => $actorUserId,
                ];
                $updatePayload = $this->appendScheduleCategoryPayload($updatePayload, $titleValue);
                $updatePayload = $this->appendLegacyCompatiblePayload($updatePayload, (string) $matchedItem->name, (string) ($labelByCode->get($labelCode)->name ?? '사용자별'));

                $existing->update($updatePayload);
                $existing->refresh();
                $this->syncVehicleUsageLogFromImport(
                    supply: $existing,
                    matchedUser: $matchedUser,
                    matchedItem: $matchedItem,
                    startsAt: $startsAt,
                    actorUserId: $actorUserId,
                    vehicleScheduleNo: $vehicleScheduleNo,
                    usagePurpose: $usagePurposeValue,
                    arrivalLocation: $arrivalLocationValue,
                    remark: $remarkValue,
                    odometerBefore: $odometerBefore,
                    odometerAfter: $odometerAfter,
                    distance: $distance,
                    isVehicleLogFormat: $isVehicleLogFormat,
                );
                $this->registerSupplyInIndex($existing, $vehicleScheduleNo);
                $importedSupplyIds[] = (int) $existing->id;
                $importedRowDates[] = $date->toDateString();
                $updated++;

                continue;
            }

            if ($ambiguousMatches > 1) {
                $errors[] = "{$excelRowNumber}행: 동일 조건의 기존 일정이 {$ambiguousMatches}건 있어 반영하지 않았습니다.";
                $skipped++;

                continue;
            }

            $createPayload = [
                'user_id' => (int) $matchedUser->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'shared_supply_item_id' => (int) $matchedItem->id,
                'shared_supply_label_id' => $labelId,
                'title' => $titleValue,
                'purpose' => $purposeValue !== '' ? $purposeValue : null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ];
            $createPayload = $this->appendScheduleCategoryPayload($createPayload, $titleValue);
            $createPayload = $this->appendLegacyCompatiblePayload($createPayload, (string) $matchedItem->name, (string) ($labelByCode->get($labelCode)->name ?? '사용자별'));

            $created = SharedSupply::query()->create($createPayload);
            $this->syncVehicleUsageLogFromImport(
                supply: $created,
                matchedUser: $matchedUser,
                matchedItem: $matchedItem,
                startsAt: $startsAt,
                actorUserId: $actorUserId,
                vehicleScheduleNo: $vehicleScheduleNo,
                usagePurpose: $usagePurposeValue,
                arrivalLocation: $arrivalLocationValue,
                remark: $remarkValue,
                odometerBefore: $odometerBefore,
                odometerAfter: $odometerAfter,
                distance: $distance,
                isVehicleLogFormat: $isVehicleLogFormat,
            );
            $this->registerSupplyInIndex($created, $vehicleScheduleNo);
            $importedSupplyIds[] = (int) $created->id;
            $importedRowDates[] = $date->toDateString();
            $inserted++;
        }

        // 복원 모드: 업로드 시 기존 행 자동 삭제를 수행하지 않는다.
        $deleted = 0;

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'deleted' => $deleted,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function findHeaderRowIndex(array $rows): ?int
    {
        for ($i = 0; $i < min(20, count($rows)); $i++) {
            $headerMap = $this->buildHeaderMap($rows[$i] ?? []);
            $hasScheduleHeaders = collect(['일자', '시작시간', '종료시간', '사용자명', '제목'])
                ->every(static fn (string $h): bool => array_key_exists($h, $headerMap));
            $hasVehicleLogHeaders = collect(['차량일정번호', '사용자명', '물품명'])
                ->every(static fn (string $h): bool => array_key_exists($h, $headerMap));
            if ($hasScheduleHeaders || $hasVehicleLogHeaders) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $value) {
            $header = $this->canonicalHeader(trim((string) $value));
            if ($header !== null) {
                $map[$header] = $index;
            }
        }

        return $map;
    }

    private function canonicalHeader(string $header): ?string
    {
        $normalized = $this->normalizeKey($header);
        if ($normalized === '') {
            return null;
        }

        if ((str_contains($normalized, '일정') && str_contains($normalized, 'no'))
            || (str_contains($normalized, '일자') && str_contains($normalized, 'no'))
            || str_contains($normalized, '일정번호')) {
            return '차량일정번호';
        }

        if (str_contains($normalized, '일자')) {
            return '일자';
        }

        if (str_contains($normalized, '시작') && str_contains($normalized, '시간')) {
            return '시작시간';
        }

        if (str_contains($normalized, '종료') && str_contains($normalized, '시간')) {
            return '종료시간';
        }

        if (str_contains($normalized, '작성자')
            || str_contains($normalized, '사용자')
            || str_contains($normalized, '참석자')
            || str_contains($normalized, '성명')) {
            return '사용자명';
        }

        if (str_contains($normalized, '제목')) {
            return '제목';
        }

        if (str_contains($normalized, '장소') || str_contains($normalized, '적요')) {
            return '적요';
        }

        if ((str_contains($normalized, '도착') && str_contains($normalized, '위치'))
            || (str_contains($normalized, '방문') && str_contains($normalized, '장소'))) {
            return '도착위치';
        }

        if ((str_contains($normalized, '사용') && str_contains($normalized, '목적'))
            || str_contains($normalized, '용도')) {
            return '사용목적명';
        }

        if (str_contains($normalized, '이동수단') || str_contains($normalized, '차량명')) {
            return '물품명';
        }

        if (str_contains($normalized, '물품') || str_contains($normalized, '공용품')) {
            return '물품명';
        }

        $aliases = [
            '일자' => ['일자', '일자요일'],
            '차량일정번호' => ['일정No.', '일정No', '일정번호', '일자No.', '일자No', '일자번호'],
            '시작시간' => ['시작시간'],
            '종료시간' => ['종료시간'],
            '물품명' => ['물품명', '이동수단명', '공용품', '공용품코드', '공용품코드리스트'],
            '사용자명' => ['사용자명', '작성자명', '작성자', '참석자성명', '참석자명', '성명'],
            '제목' => ['제목'],
            '적요' => ['적요', '장소'],
            '도착위치' => ['도착위치', '도착 위치', '방문위치', '방문 위치'],
            '사용목적명' => ['사용목적명', '사용목적', '용도'],
            '주행전계기판거리' => ['주행전 계기판거리'],
            '주행후계기판거리' => ['주행후 계기판거리'],
            '운행거리' => ['운행거리'],
        ];

        foreach ($aliases as $canonical => $aliasValues) {
            foreach ($aliasValues as $alias) {
                if ($normalized === $this->normalizeKey($alias)) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    /**
     * @param  array{inserted:int,updated:int,deleted?:int,skipped:int,errors:array<int, string>}  $result
     */
    private function isHeaderMissingResult(array $result): bool
    {
        return ($result['inserted'] ?? 0) === 0
            && ($result['updated'] ?? 0) === 0
            && ($result['deleted'] ?? 0) === 0
            && ($result['skipped'] ?? 0) === 0
            && count($result['errors'] ?? []) === 1
            && str_contains((string) ($result['errors'][0] ?? ''), '헤더');
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{from: Carbon, to: Carbon}|null
     */
    private function extractSyncDateRangeFromRows(array $rows, int $headerIndex): ?array
    {
        for ($i = 0; $i < $headerIndex; $i++) {
            $text = collect($rows[$i] ?? [])
                ->map(static fn (mixed $cell): string => trim((string) $cell))
                ->filter(static fn (string $cell): bool => $cell !== '')
                ->implode(' ');

            $range = $this->parseDateRangeFromBannerText($text);
            if ($range !== null) {
                return $range;
            }
        }

        return null;
    }

    /**
     * @return array{from: Carbon, to: Carbon}|null
     */
    private function parseDateRangeFromBannerText(string $text): ?array
    {
        if ($text === '') {
            return null;
        }

        if (preg_match('/(\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2})\s*[~〜\-–]\s*(\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2})/u', $text, $matches) !== 1) {
            return null;
        }

        $from = $this->parseDateValue($matches[1] ?? null);
        $to = $this->parseDateValue($matches[2] ?? null);
        if (! $from instanceof Carbon || ! $to instanceof Carbon) {
            return null;
        }

        return [
            'from' => $from->copy()->startOfDay(),
            'to' => $to->copy()->endOfDay(),
        ];
    }

    private function initializeSchemaFlags(): void
    {
        if ($this->hasEmployeeTable === null) {
            $this->hasEmployeeTable = Schema::hasTable('employee');
        }

        if ($this->hasScheduleCategoryCodeColumn === null) {
            $this->hasScheduleCategoryCodeColumn = Schema::hasColumn('shared_supplies', 'schedule_category_code');
        }

        if ($this->hasLegacyItemNameColumn === null) {
            $this->hasLegacyItemNameColumn = Schema::hasColumn('shared_supplies', 'item_name');
        }

        if ($this->hasLegacyLabelColumn === null) {
            $this->hasLegacyLabelColumn = Schema::hasColumn('shared_supplies', 'label');
        }

        if ($this->canSyncVehicleUsageLogs === null) {
            $this->canSyncVehicleUsageLogs = Schema::hasTable('vehicle_usage_logs')
                && Schema::hasColumn('vehicle_usage_logs', 'shared_supply_id')
                && Schema::hasColumn('vehicle_usage_logs', 'user_id')
                && Schema::hasColumn('vehicle_usage_logs', 'vehicle_name');
        }

        if ($this->hasVehicleLogArrivalLocationColumn === null) {
            $this->hasVehicleLogArrivalLocationColumn = (bool) $this->canSyncVehicleUsageLogs
                && Schema::hasColumn('vehicle_usage_logs', 'arrival_location');
        }
    }

    private function resetMatchIndex(): void
    {
        $this->scheduleRefIndex = [];
        $this->looseMatchIndex = [];
        $this->vehicleLogsBySupplyId = [];
        $this->matchIndexIsLoaded = false;
    }

    private function preloadMatchIndex(Carbon $rangeFrom, Carbon $rangeTo): void
    {
        $supplies = SharedSupply::query()
            ->with(['vehicleUsageLog'])
            ->whereDate('starts_at', '>=', $rangeFrom->toDateString())
            ->whereDate('starts_at', '<=', $rangeTo->toDateString())
            ->get();

        foreach ($supplies as $supply) {
            $this->registerSupplyInIndex($supply);
        }

        $this->matchIndexIsLoaded = true;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int>  $headerMap
     * @return array{from: Carbon, to: Carbon}|null
     */
    private function scanImportDateRange(array $rows, int $headerIndex, array $headerMap, bool $isVehicleLogFormat): ?array
    {
        $dates = [];

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i] ?? [];
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $vehicleScheduleNo = trim((string) $this->cellValue($row, $headerMap, '차량일정번호'));
            $dateValue = $this->cellValue($row, $headerMap, '일자');
            if ($isVehicleLogFormat && trim((string) $dateValue) === '') {
                $derivedDate = $this->extractDateFromVehicleScheduleNo($vehicleScheduleNo);
                if ($derivedDate !== null) {
                    $dateValue = $derivedDate;
                }
            }

            $itemValue = trim((string) $this->cellValue($row, $headerMap, '물품명'));
            $userNameValue = trim((string) $this->cellValue($row, $headerMap, '사용자명'));
            $titleValue = trim((string) $this->cellValue($row, $headerMap, '제목'));
            if ($isVehicleLogFormat && $titleValue === '') {
                $titleValue = '[출장 차량배차] 신청 및 예약';
            }

            if ($itemValue === '' && $userNameValue === '' && $titleValue === '') {
                continue;
            }

            $date = $this->parseDateValue($dateValue);
            if ($date instanceof Carbon) {
                $dates[] = $date->toDateString();
            }
        }

        if ($dates === []) {
            return null;
        }

        return [
            'from' => Carbon::parse(min($dates))->startOfDay(),
            'to' => Carbon::parse(max($dates))->endOfDay(),
        ];
    }

    private function registerSupplyInIndex(SharedSupply $supply, ?string $vehicleScheduleNo = null): void
    {
        $startsAt = $supply->starts_at instanceof Carbon
            ? $supply->starts_at
            : Carbon::parse((string) $supply->starts_at);

        $key = $this->looseMatchKey(
            (int) $supply->user_id,
            (int) $supply->shared_supply_item_id,
            $startsAt->toDateString(),
            (string) $supply->title,
        );

        if (! isset($this->looseMatchIndex[$key])) {
            $this->looseMatchIndex[$key] = collect();
        }

        $this->looseMatchIndex[$key] = $this->looseMatchIndex[$key]
            ->reject(static fn (SharedSupply $candidate): bool => (int) $candidate->id === (int) $supply->id)
            ->push($supply)
            ->values();

        if ($vehicleScheduleNo !== null && $vehicleScheduleNo !== '') {
            $this->scheduleRefIndex[$this->scheduleRefIndexKey((int) $supply->user_id, $vehicleScheduleNo)] = $supply;
        }

        $remarks = (string) ($supply->vehicleUsageLog?->remarks ?? '');
        if ($remarks !== '' && preg_match_all('/\[excel-schedule:([^\]]+)\]/u', $remarks, $matches)) {
            foreach ($matches[1] as $scheduleNo) {
                $this->scheduleRefIndex[$this->scheduleRefIndexKey((int) $supply->user_id, (string) $scheduleNo)] = $supply;
            }
        }

        if ($supply->vehicleUsageLog instanceof VehicleUsageLog) {
            $this->vehicleLogsBySupplyId[(int) $supply->id] = $supply->vehicleUsageLog;
        }
    }

    private function looseMatchKey(int $userId, int $itemId, string $date, string $title): string
    {
        return $userId.'|'.$itemId.'|'.$date.'|'.$title;
    }

    private function looseMatchKeyPrefix(int $userId, int $itemId, string $date): string
    {
        return $userId.'|'.$itemId.'|'.$date.'|';
    }

    private function scheduleRefIndexKey(int $userId, string $vehicleScheduleNo): string
    {
        return $userId.'|'.$vehicleScheduleNo;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function syncCalendarForSupplies(array $ids): void
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return;
        }

        SharedSupply::query()
            ->with('item')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->each(static fn (SharedSupply $supply) => app(SharedSupplyCalendarSync::class)->sync($supply));
    }

    /**
     * @return array{supply: ?SharedSupply, ambiguousCount: int}
     */
    private function resolveSupplyMatch(
        bool $isVehicleLogFormat,
        string $vehicleScheduleNo,
        int $userId,
        int $itemId,
        string $itemName,
        Carbon $startsAt,
        string $title,
        string $usagePurpose,
        ?int $odometerBefore,
    ): array {
        if ($isVehicleLogFormat && $vehicleScheduleNo !== '') {
            $bySchedule = $this->findSupplyByVehicleScheduleRef($vehicleScheduleNo, $userId);
            if ($bySchedule instanceof SharedSupply) {
                return ['supply' => $bySchedule, 'ambiguousCount' => 0];
            }
        }

        $matches = $this->queryLooseSupplyMatches(
            isVehicleLogFormat: $isVehicleLogFormat,
            userId: $userId,
            itemId: $itemId,
            itemName: $itemName,
            startsAt: $startsAt,
            title: $title,
            usagePurpose: $usagePurpose,
            odometerBefore: $odometerBefore,
        );

        return [
            'supply' => $matches->count() === 1 ? $matches->first() : null,
            'ambiguousCount' => $matches->count(),
        ];
    }

    /**
     * @param  array{from: Carbon, to: Carbon}|null  $bannerSyncRange
     * @param  array<int, string>  $importedRowDates
     * @param  array<int, int>  $importedSupplyIds
     */
    private function deleteSuppliesOutsideSnapshot(?array $bannerSyncRange, array $importedRowDates, array $importedSupplyIds): int
    {
        $importedSupplyIds = array_values(array_unique($importedSupplyIds));
        if ($importedSupplyIds === []) {
            return 0;
        }

        $rangeFrom = $bannerSyncRange['from'] ?? null;
        $rangeTo = $bannerSyncRange['to'] ?? null;

        if ($rangeFrom === null || $rangeTo === null) {
            if ($importedRowDates === []) {
                return 0;
            }

            $rangeFrom = Carbon::parse(min($importedRowDates))->startOfDay();
            $rangeTo = Carbon::parse(max($importedRowDates))->endOfDay();
        }

        $idsToDelete = SharedSupply::query()
            ->whereDate('starts_at', '>=', $rangeFrom->toDateString())
            ->whereDate('starts_at', '<=', $rangeTo->toDateString())
            ->whereNotIn('id', $importedSupplyIds)
            ->pluck('id');

        if ($idsToDelete->isEmpty()) {
            return 0;
        }

        $ids = $idsToDelete->all();

        if (Schema::hasColumn('team_schedules', 'source_type') && Schema::hasColumn('team_schedules', 'source_id')) {
            TeamSchedule::query()
                ->where('source_type', 'shared_supply')
                ->whereIn('source_id', $ids)
                ->delete();
        }

        if ($this->canSyncVehicleUsageLogs) {
            VehicleUsageLog::query()->whereIn('shared_supply_id', $ids)->delete();
        }

        SharedSupply::query()->whereIn('id', $ids)->delete();

        foreach ($ids as $id) {
            unset($this->vehicleLogsBySupplyId[(int) $id]);
        }

        return count($ids);
    }

    /**
     * @return Collection<int, SharedSupply>
     */
    private function queryLooseSupplyMatches(
        bool $isVehicleLogFormat,
        int $userId,
        int $itemId,
        string $itemName,
        Carbon $startsAt,
        string $title,
        string $usagePurpose,
        ?int $odometerBefore,
    ): Collection {
        $dayStart = $startsAt->copy()->startOfDay();
        $dayEnd = $startsAt->copy()->endOfDay();

        if ($this->matchIndexIsLoaded) {
            if ($isVehicleLogFormat) {
                $keyPrefix = $this->looseMatchKeyPrefix($userId, $itemId, $startsAt->toDateString());
                $matches = collect($this->looseMatchIndex)
                    ->filter(static fn (Collection $bucket, string $key): bool => str_starts_with($key, $keyPrefix))
                    ->flatMap(static fn (Collection $bucket): Collection => $bucket)
                    ->unique('id')
                    ->values();
            } else {
                $key = $this->looseMatchKey($userId, $itemId, $startsAt->toDateString(), $title);
                $matches = ($this->looseMatchIndex[$key] ?? collect());
            }

            $matches = $matches
                ->filter(static fn (SharedSupply $supply): bool => $supply->starts_at->betweenIncluded($dayStart, $dayEnd))
                ->values();
        } else {
            $query = SharedSupply::query()
                ->with(['vehicleUsageLog'])
                ->where('user_id', $userId)
                ->where('shared_supply_item_id', $itemId)
                ->whereBetween('starts_at', [$dayStart, $dayEnd]);

            if (! $isVehicleLogFormat && $title !== '') {
                $query->where('title', $title);
            }

            $matches = $query->get();
        }

        if ($matches->count() <= 1) {
            return $matches;
        }

        if ($isVehicleLogFormat) {
            $matches = $this->narrowVehicleSupplyMatches(
                matches: $matches,
                itemName: $itemName,
                usagePurpose: $usagePurpose,
                odometerBefore: $odometerBefore,
                drivenOn: $startsAt->toDateString(),
            );
        } elseif ($usagePurpose !== '') {
            $matches = $matches->filter(
                static fn (SharedSupply $supply): bool => str_contains((string) ($supply->purpose ?? ''), $usagePurpose),
            )->values();
        }

        return $matches;
    }

    /**
     * @param  Collection<int, SharedSupply>  $matches
     * @return Collection<int, SharedSupply>
     */
    private function narrowVehicleSupplyMatches(
        Collection $matches,
        string $itemName,
        string $usagePurpose,
        ?int $odometerBefore,
        string $drivenOn,
    ): Collection {
        if ($usagePurpose !== '') {
            $byPurpose = $matches->filter(function (SharedSupply $supply) use ($usagePurpose, $drivenOn): bool {
                $log = $supply->vehicleUsageLog;
                if ($log instanceof VehicleUsageLog) {
                    return (string) ($log->usage_purpose_name ?? '') === $usagePurpose
                        && (string) ($log->driven_on?->toDateString() ?? '') === $drivenOn;
                }

                return str_contains((string) ($supply->purpose ?? ''), $usagePurpose);
            })->values();

            if ($byPurpose->count() >= 1) {
                $matches = $byPurpose;
            }
        }

        if ($matches->count() > 1 && $odometerBefore !== null) {
            $byOdometer = $matches->filter(
                static fn (SharedSupply $supply): bool => (int) ($supply->vehicleUsageLog?->odometer_before ?? -1) === $odometerBefore,
            )->values();

            if ($byOdometer->count() >= 1) {
                $matches = $byOdometer;
            }
        }

        if ($matches->count() > 1) {
            $matches = $matches->filter(
                static fn (SharedSupply $supply): bool => (string) ($supply->vehicleUsageLog?->vehicle_name ?? '') === $itemName,
            )->values();
        }

        return $matches;
    }

    private function findSupplyByVehicleScheduleRef(string $vehicleScheduleNo, int $userId): ?SharedSupply
    {
        $indexKey = $this->scheduleRefIndexKey($userId, $vehicleScheduleNo);
        if (isset($this->scheduleRefIndex[$indexKey])) {
            return $this->scheduleRefIndex[$indexKey];
        }

        if (! $this->canSyncVehicleUsageLogs || ! Schema::hasColumn('vehicle_usage_logs', 'remarks')) {
            return null;
        }

        $tag = $this->vehicleScheduleRefTag($vehicleScheduleNo);
        $log = VehicleUsageLog::query()
            ->where('user_id', $userId)
            ->where('remarks', 'like', '%'.$tag.'%')
            ->orderByDesc('id')
            ->first();

        if (! $log instanceof VehicleUsageLog || $log->shared_supply_id === null) {
            return null;
        }

        return SharedSupply::query()->with(['vehicleUsageLog'])->find((int) $log->shared_supply_id);
    }

    private function vehicleScheduleRefTag(string $vehicleScheduleNo): string
    {
        return self::VEHICLE_SCHEDULE_REF_PREFIX.$vehicleScheduleNo.']';
    }

    private function formatVehicleLogRemarks(string $vehicleScheduleNo, string $remark): ?string
    {
        $tag = $this->vehicleScheduleRefTag($vehicleScheduleNo);
        $userRemark = trim($remark);

        if ($userRemark === '') {
            return $tag;
        }

        if (str_contains($userRemark, $tag)) {
            return $userRemark;
        }

        return $tag.' '.$userRemark;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     */
    private function cellValue(array $row, array $headerMap, string $header): mixed
    {
        $index = $headerMap[$header] ?? null;

        return $index === null ? null : ($row[$index] ?? null);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{time: string, dayOffset: int}|null
     */
    private function parseTime(mixed $value, bool $isEndTime): ?array
    {
        if ($value instanceof DateTimeInterface) {
            return [
                'time' => Carbon::instance($value)->format('H:i'),
                'dayOffset' => 0,
            ];
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $text, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($minute < 0 || $minute > 59) {
            return null;
        }

        if ($hour === 24 && $minute === 0) {
            return [
                'time' => '00:00',
                'dayOffset' => 1,
            ];
        }

        if ($hour < 0 || $hour > 23) {
            return null;
        }

        return [
            'time' => sprintf('%02d:%02d', $hour, $minute),
            'dayOffset' => 0,
        ];
    }

    private function parseDateValue(mixed $value): ?Carbon
    {
        $parsed = ExcelSerialDate::parse($value);
        if ($parsed instanceof Carbon) {
            return $parsed->startOfDay();
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);
        if ($text === '') {
            return null;
        }

        // 예: 2026/06/02 (화), 2026-06-02(Thu), 2026.06.02
        $text = preg_replace('/\s*\([^)]*\)\s*/u', '', $text) ?? $text;
        $text = str_replace(['년', '월', '.'], ['/', '/', '/'], $text);
        $text = str_replace('일', '', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        $formats = ['Y/m/d', 'Y-m-d', 'y/m/d', 'y-m-d'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $text);
                if ($date instanceof Carbon) {
                    return $date->startOfDay();
                }
            } catch (\Throwable) {
                // 다음 포맷으로 진행
            }
        }

        try {
            return Carbon::parse($text)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, User>  $users
     */
    private function matchUserByName(array $users, string $rawName): ?User
    {
        $name = trim($rawName);
        if ($name === '') {
            return null;
        }

        $candidates = $this->extractRawNameCandidates($name);

        foreach ($candidates as $candidate) {
            $exact = collect($users)->first(function (User $u) use ($candidate): bool {
                return in_array($candidate, $this->userNameCandidates($u), true);
            });
            if ($exact instanceof User) {
                return $exact;
            }
        }

        foreach ($candidates as $candidate) {
            $normalizedCandidate = $this->normalizeKey($candidate);
            if ($normalizedCandidate === '') {
                continue;
            }

            $normalizedMatch = collect($users)->first(function (User $u) use ($normalizedCandidate): bool {
                foreach ($this->userNameCandidates($u) as $nameCandidate) {
                    if ($this->normalizeKey($nameCandidate) === $normalizedCandidate) {
                        return true;
                    }
                }

                return false;
            });
            if ($normalizedMatch instanceof User) {
                return $normalizedMatch;
            }

            $fuzzyMatch = collect($users)->first(function (User $u) use ($normalizedCandidate): bool {
                foreach ($this->userNameCandidates($u) as $nameCandidate) {
                    $normalizedUser = $this->normalizeKey($nameCandidate);
                    if ($normalizedUser === '' || mb_strlen($normalizedCandidate) < 2) {
                        continue;
                    }

                    if (str_contains($normalizedUser, $normalizedCandidate)
                        || str_contains($normalizedCandidate, $normalizedUser)) {
                        return true;
                    }
                }

                return false;
            });
            if ($fuzzyMatch instanceof User) {
                return $fuzzyMatch;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractRawNameCandidates(string $name): array
    {
        $candidates = [$name];
        $beforeParen = trim((string) preg_replace('/\s*\(.*$/u', '', $name));
        if ($beforeParen !== '' && $beforeParen !== $name) {
            $candidates[] = $beforeParen;
        }

        if (preg_match_all('/\(([^)]*)\)/u', $name, $matches) > 0) {
            foreach ($matches[1] as $inside) {
                $insideText = trim((string) $inside);
                if ($insideText !== '') {
                    $candidates[] = $insideText;
                }
            }
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $v): bool => $v !== '')));
    }

    /**
     * @return array<int, string>
     */
    private function userNameCandidates(User $user): array
    {
        $candidates = [trim((string) $user->name)];

        if ($user->relationLoaded('employee') && $user->employee !== null) {
            $koreanName = trim((string) ($user->employee->KOREANAME ?? ''));
            $englishName = trim((string) ($user->employee->ENGLISHNAME ?? ''));
            if ($koreanName !== '') {
                $candidates[] = $koreanName;
            }
            if ($englishName !== '') {
                $candidates[] = $englishName;
            }
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $v): bool => $v !== '')));
    }

    /**
     * @param  array<int, SharedSupplyItem>  $items
     */
    private function matchItem(array $items, string $rawItem): ?SharedSupplyItem
    {
        $item = trim($rawItem);
        if ($item === '') {
            return null;
        }

        $exactByName = collect($items)->first(fn (SharedSupplyItem $i): bool => trim((string) $i->name) === $item);
        if ($exactByName instanceof SharedSupplyItem) {
            return $exactByName;
        }

        $exactByCode = collect($items)->first(fn (SharedSupplyItem $i): bool => trim((string) $i->code) === $item);
        if ($exactByCode instanceof SharedSupplyItem) {
            return $exactByCode;
        }

        $normalized = $this->normalizeKey($item);

        $normalizedMatch = collect($items)->first(function (SharedSupplyItem $i) use ($normalized): bool {
            return $this->normalizeKey((string) $i->name) === $normalized
                || $this->normalizeKey((string) $i->code) === $normalized;
        });
        if ($normalizedMatch instanceof SharedSupplyItem) {
            return $normalizedMatch;
        }

        // 차량명 표기 흔들림(예: 아반테/아반떼, 지역 메모 suffix)은 차량번호로 우선 매칭한다.
        $plateNumber = $this->extractVehiclePlateNumber($item);
        if ($plateNumber !== null) {
            $matchedByPlate = collect($items)->first(function (SharedSupplyItem $i) use ($plateNumber): bool {
                $itemPlate = $this->extractVehiclePlateNumber((string) $i->name);

                return $itemPlate !== null && $itemPlate === $plateNumber;
            });
            if ($matchedByPlate instanceof SharedSupplyItem) {
                return $matchedByPlate;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, SharedSupplyItem>  $items
     */
    private function resolveItemForImport(Collection $items, string $rawItem, string $title): ?SharedSupplyItem
    {
        $matchedItem = $this->matchItem($items->all(), $rawItem);
        if ($matchedItem instanceof SharedSupplyItem) {
            return $matchedItem;
        }

        if (trim($rawItem) !== '') {
            return null;
        }

        $itemName = $this->itemNameFromTitle($title);
        if ($itemName === '') {
            return null;
        }

        $matchedByTitle = $this->matchItem($items->all(), $itemName);
        if ($matchedByTitle instanceof SharedSupplyItem) {
            return $matchedByTitle;
        }

        $createdItem = SharedSupplyItem::query()->create([
            'code' => $this->generateNextItemCode(),
            'name' => $itemName,
            'is_active' => true,
            'sort_order' => ((int) SharedSupplyItem::query()->max('sort_order')) + 1,
        ]);
        $items->push($createdItem);

        return $createdItem;
    }

    private function itemNameFromTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        if (preg_match('/^\[휴가\]\s*(.+)$/u', $title, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return $title;
    }

    private function generateNextItemCode(): string
    {
        $maxNumericCode = SharedSupplyItem::query()
            ->pluck('code')
            ->map(fn (string $code): int => ctype_digit($code) ? (int) $code : 0)
            ->max() ?? 0;

        return str_pad((string) ($maxNumericCode + 1), 5, '0', STR_PAD_LEFT);
    }

    private function matchOrCreateUserByEmployeeMaster(string $rawName): ?User
    {
        if (! $this->hasEmployeeTable) {
            return null;
        }

        $candidates = $this->extractRawNameCandidates(trim($rawName));
        if ($candidates === []) {
            return null;
        }

        $employees = Employee::query()
            ->select(['EMPNO', 'EMAIL', 'KOREANAME', 'ENGLISHNAME'])
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhere('KOREANAME', $candidate)
                        ->orWhere('ENGLISHNAME', $candidate);
                }
            })
            ->limit(30)
            ->get();

        if ($employees->isEmpty()) {
            return null;
        }

        foreach ($employees as $employee) {
            $empNo = trim((string) ($employee->EMPNO ?? ''));
            if ($empNo !== '') {
                $existing = User::query()->where('employee_empno', $empNo)->first();
                if ($existing instanceof User) {
                    return $existing;
                }
            }

            $email = mb_strtolower(trim((string) ($employee->EMAIL ?? '')));
            if ($email !== '') {
                $existingByEmail = User::query()
                    ->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$email])
                    ->first();
                if ($existingByEmail instanceof User) {
                    return $existingByEmail;
                }
            }
        }

        // 동일 이름이 여러 직원으로 매칭되면 오매칭 위험이 있어 자동 생성하지 않는다.
        if ($employees->count() !== 1) {
            return null;
        }

        return $this->createUserFromEmployee($employees->first());
    }

    private function createUserFromEmployee(?Employee $employee): ?User
    {
        if (! $employee instanceof Employee) {
            return null;
        }

        $empNo = trim((string) ($employee->EMPNO ?? ''));
        $name = trim((string) ($employee->KOREANAME ?? ''));
        if ($name === '') {
            $name = trim((string) ($employee->ENGLISHNAME ?? ''));
        }
        if ($name === '') {
            $name = $empNo !== '' ? 'EMP-'.$empNo : 'Employee User';
        }

        $preferredEmail = mb_strtolower(trim((string) ($employee->EMAIL ?? '')));
        $email = $this->resolveUniqueUserEmail($preferredEmail, $empNo);

        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'employee_empno' => $empNo !== '' ? $empNo : null,
            'password' => Str::password(24),
            'is_admin' => false,
            'is_active' => true,
        ]);
    }

    private function resolveUniqueUserEmail(string $preferredEmail, string $empNo): string
    {
        $candidate = $preferredEmail;
        if ($candidate === '') {
            $sanitizedEmpNo = preg_replace('/[^a-zA-Z0-9]/', '', $empNo) ?? '';
            $seed = $sanitizedEmpNo !== '' ? mb_strtolower($sanitizedEmpNo) : 'employee-'.Str::lower(Str::random(8));
            $candidate = $seed.'@local.mocchi';
        }

        $candidate = mb_strtolower($candidate);
        if (! User::query()->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$candidate])->exists()) {
            return $candidate;
        }

        $parts = explode('@', $candidate, 2);
        $local = $parts[0] ?? 'employee';
        $domain = $parts[1] ?? 'local.mocchi';

        for ($suffix = 1; $suffix <= 9999; $suffix++) {
            $retry = $local.'+'.$suffix.'@'.$domain;
            if (! User::query()->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$retry])->exists()) {
                return $retry;
            }
        }

        return 'employee-'.Str::lower(Str::random(12)).'@local.mocchi';
    }

    private function resolveLabelCode(string $title, string $itemName): string
    {
        $titleText = mb_strtolower($title);
        $itemText = mb_strtolower($itemName);

        if (str_contains($titleText, '회의실') || str_contains($itemText, 'room')) {
            return '02';
        }

        if (str_contains($titleText, '차량배차')) {
            return '01';
        }

        return '01';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function appendScheduleCategoryPayload(array $payload, string $title): array
    {
        if (! $this->hasScheduleCategoryCodeColumn) {
            return $payload;
        }

        $payload['schedule_category_code'] = $this->resolveScheduleCategoryCode($title);

        return $payload;
    }

    private function resolveScheduleCategoryCode(string $title): ?string
    {
        if (preg_match('/^\[([^\]]+)\]/u', trim($title), $matches) !== 1) {
            return null;
        }

        return match (trim((string) ($matches[1] ?? ''))) {
            '휴가' => '001',
            '출장', '출장 차량배차' => '002',
            '해외출장' => '003',
            '전체회의' => '004',
            '본부회의' => '005',
            '팀회의', '회의실' => str_contains($title, '(팀 회의)') ? '006' : null,
            '사내외행사' => '007',
            '경조사' => '009',
            '건강검진' => '011',
            '사내외업무' => '012',
            default => null,
        };
    }

    private function normalizeKey(string $value): string
    {
        $normalized = class_exists(\Normalizer::class)
            ? (\Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value)
            : $value;
        $lower = mb_strtolower(trim($normalized));

        return preg_replace('/[\s\p{P}\p{S}]+/u', '', $lower) ?? $lower;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function appendLegacyCompatiblePayload(array $payload, string $itemName, string $labelName): array
    {
        $hasLegacyItemName = $this->hasLegacyItemNameColumn;
        if ($hasLegacyItemName === null) {
            $hasLegacyItemName = Schema::hasColumn('shared_supplies', 'item_name');
            $this->hasLegacyItemNameColumn = $hasLegacyItemName;
        }

        if ($hasLegacyItemName) {
            $payload['item_name'] = $itemName;
        }

        $hasLegacyLabel = $this->hasLegacyLabelColumn;
        if ($hasLegacyLabel === null) {
            $hasLegacyLabel = Schema::hasColumn('shared_supplies', 'label');
            $this->hasLegacyLabelColumn = $hasLegacyLabel;
        }

        if ($hasLegacyLabel) {
            $payload['label'] = $labelName;
        }

        return $payload;
    }

    private function extractDateFromVehicleScheduleNo(string $scheduleNo): ?string
    {
        if ($scheduleNo === '') {
            return null;
        }

        if (preg_match('/^(\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2})/u', $scheduleNo, $matches) !== 1) {
            return null;
        }

        return str_replace(['.', '-'], '/', trim((string) ($matches[1] ?? '')));
    }

    /**
     * @return array{start: string, end: string}
     */
    private function buildDefaultTimeRangeForVehicleLog(int $excelRowNumber): array
    {
        $slot = max(0, $excelRowNumber - 1);
        $startMinutes = 8 * 60 + (($slot * 10) % (14 * 60));
        $endMinutes = $startMinutes + 10;

        return [
            'start' => sprintf('%02d:%02d', intdiv($startMinutes, 60), $startMinutes % 60),
            'end' => sprintf('%02d:%02d', intdiv($endMinutes, 60), $endMinutes % 60),
        ];
    }

    private function mergePurposeForVehicleLog(string $usagePurpose, string $remark): string
    {
        if ($usagePurpose === '') {
            return $remark;
        }

        if ($remark === '') {
            return $usagePurpose;
        }

        return $usagePurpose.' / '.$remark;
    }

    private function extractVehiclePlateNumber(string $text): ?string
    {
        if (preg_match('/\d{2,3}[가-힣]\d{4}/u', $text, $matches) !== 1) {
            return null;
        }

        return trim((string) ($matches[0] ?? '')) ?: null;
    }

    private function parseIntegerValue(mixed $value): ?int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $digits = preg_replace('/[^\d\-]/', '', $text) ?? '';
        if ($digits === '' || $digits === '-') {
            return null;
        }

        return (int) $digits;
    }

    private function syncVehicleUsageLogFromImport(
        SharedSupply $supply,
        User $matchedUser,
        SharedSupplyItem $matchedItem,
        Carbon $startsAt,
        int $actorUserId,
        string $vehicleScheduleNo,
        string $usagePurpose,
        string $arrivalLocation,
        string $remark,
        ?int $odometerBefore,
        ?int $odometerAfter,
        ?int $distance,
        bool $isVehicleLogFormat
    ): void {
        if (! $isVehicleLogFormat || ! $this->canSyncVehicleUsageLogs) {
            return;
        }

        if ($odometerBefore === null
            && $odometerAfter === null
            && $distance === null
            && $usagePurpose === ''
            && $arrivalLocation === ''
            && $remark === ''
            && $vehicleScheduleNo === '') {
            return;
        }

        $payload = [
            'user_id' => (int) $matchedUser->id,
            'vehicle_name' => (string) $matchedItem->name,
            'usage_purpose_name' => $usagePurpose !== '' ? $usagePurpose : null,
            'remarks' => $vehicleScheduleNo !== ''
                ? $this->formatVehicleLogRemarks($vehicleScheduleNo, $remark)
                : ($remark !== '' ? $remark : null),
            'driven_on' => $startsAt->toDateString(),
            'updated_by' => $actorUserId,
        ];

        if ($odometerBefore !== null) {
            $payload['odometer_before'] = $odometerBefore;
        }

        if ($odometerAfter !== null) {
            $payload['odometer_after'] = $odometerAfter;
        }

        $resolvedDistance = $distance ?? (($odometerBefore !== null && $odometerAfter !== null)
            ? max(0, $odometerAfter - $odometerBefore)
            : null);
        if ($resolvedDistance !== null) {
            $payload['distance'] = $resolvedDistance;
        }
        if ($this->hasVehicleLogArrivalLocationColumn) {
            $payload['arrival_location'] = $arrivalLocation !== '' ? $arrivalLocation : null;
        }

        $existingLog = $this->vehicleLogsBySupplyId[(int) $supply->id] ?? null;
        if ($existingLog instanceof VehicleUsageLog) {
            $existingLog->update($payload);
            $existingLog->refresh();
            $supply->setRelation('vehicleUsageLog', $existingLog);
            $this->vehicleLogsBySupplyId[(int) $supply->id] = $existingLog;

            return;
        }

        $payload['shared_supply_id'] = $supply->id;
        $payload['created_by'] = $actorUserId;
        $createdLog = VehicleUsageLog::query()->create($payload);
        $supply->setRelation('vehicleUsageLog', $createdLog);
        $this->vehicleLogsBySupplyId[(int) $supply->id] = $createdLog;
    }
}
