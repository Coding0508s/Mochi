<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * 레거시 엑셀 일련번호(1900 날짜 체계) 날짜 변환.
 *
 * Teachers.Plan_*_Support_Date 등에 45809 같은 숫자가 들어간 경우
 * Laravel datetime 캐스트가 Unix 초로 해석해 1970-01-01로 보이는 문제를 방지합니다.
 */
final class ExcelSerialDate
{
    private const EPOCH = '1899-12-30';

    /** @var int 엑셀 serial로 볼 수 있는 최소값 (대략 1968년) */
    private const SERIAL_MIN = 25_000;

    /** @var int 엑셀 serial로 볼 수 있는 최대값 (대략 2077년) */
    private const SERIAL_MAX = 65_000;

    public static function isSerial(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (! is_numeric($value)) {
            return false;
        }

        $serial = (int) $value;

        return $serial >= self::SERIAL_MIN && $serial <= self::SERIAL_MAX;
    }

    public static function fromSerial(int $serial): Carbon
    {
        return Carbon::parse(self::EPOCH)->addDays($serial)->startOfDay();
    }

    public static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return self::isEpochArtifact($value) ? null : $value->copy()->startOfDay();
        }

        if ($value instanceof DateTimeInterface) {
            $carbon = Carbon::instance($value)->startOfDay();

            return self::isEpochArtifact($carbon) ? null : $carbon;
        }

        if (self::isSerial($value)) {
            return self::fromSerial((int) $value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || $trimmed === '0000-00-00' || str_starts_with($trimmed, '0000-00-00')) {
                return null;
            }
        }

        try {
            $parsed = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return self::isEpochArtifact($parsed) ? null : $parsed;
    }

    public static function toStorageString(mixed $value): ?string
    {
        $parsed = self::parse($value);

        return $parsed?->format('Y-m-d');
    }

    public static function formatPlanMonth(mixed $value): string
    {
        $parsed = self::parse($value);

        return $parsed === null ? '' : $parsed->format('Y년 n월');
    }

    public static function isInYear(mixed $value, int $year): bool
    {
        $parsed = self::parse($value);

        return $parsed !== null && $parsed->year === $year;
    }

    public static function matchesFilterYear(mixed $value, ?int $year): bool
    {
        $parsed = self::parse($value);

        if ($parsed === null) {
            return false;
        }

        if ($year === null) {
            return true;
        }

        return $parsed->year === $year;
    }

    public static function formatPlanMonthForYear(mixed $value, int $year): string
    {
        if (! self::isInYear($value, $year)) {
            return '';
        }

        $parsed = self::parse($value);

        return $parsed === null ? '' : $parsed->format('Y년 n월');
    }

    public static function displayPlanMonth(mixed $value, ?int $year): string
    {
        if ($year === null) {
            return self::formatPlanMonth($value);
        }

        return self::formatPlanMonthForYear($value, $year);
    }

    public static function toStorageStringForYear(mixed $value, int $year): ?string
    {
        return self::isInYear($value, $year) ? self::toStorageString($value) : null;
    }

    public static function displayStorageString(mixed $value, ?int $year = null): ?string
    {
        if ($year === null) {
            return self::toStorageString($value);
        }

        return self::toStorageStringForYear($value, $year);
    }

    public static function displayCompletedWithType(mixed $dateValue, mixed $type, ?int $year): string
    {
        $date = self::displayStorageString($dateValue, $year) ?? '';
        if ($date === '') {
            return '';
        }

        if ($year !== null && ! self::matchesFilterYear($dateValue, $year)) {
            return '';
        }

        $typeLabel = trim((string) ($type ?? ''));
        if ($typeLabel === '') {
            return $date;
        }

        return $date.' ('.$typeLabel.')';
    }

    /**
     * @return array{date: string, type: string}
     */
    public static function completedDisplayParts(mixed $dateValue, mixed $type, ?int $year): array
    {
        $date = self::displayStorageString($dateValue, $year) ?? '';
        if ($date === '' || ($year !== null && ! self::matchesFilterYear($dateValue, $year))) {
            return ['date' => '', 'type' => ''];
        }

        return [
            'date' => $date,
            'type' => trim((string) ($type ?? '')),
        ];
    }

    public static function dateToSerial(CarbonInterface $date): int
    {
        return (int) Carbon::parse(self::EPOCH)->startOfDay()->diffInDays($date->copy()->startOfDay());
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function serialBoundsForYear(int $year): array
    {
        return [
            self::dateToSerial(Carbon::create($year, 1, 1)->startOfDay()),
            self::dateToSerial(Carbon::create($year, 12, 31)->startOfDay()),
        ];
    }

    /**
     * ISO 날짜·엑셀 serial(숫자) 혼재 컬럼에 연도 조건을 적용한다.
     *
     * @param  Builder<Model>  $query
     */
    public static function applyWhereYear(Builder $query, string $column, int $year): void
    {
        $query->where(function (Builder $nested) use ($column, $year): void {
            $nested->whereRaw(self::sqlColumnInYear($column, $year));
        });
    }

    public static function sqlDateValueIsNotBlank(string $qualifiedColumn): string
    {
        return self::sqlBlankSafeText($qualifiedColumn).' IS NOT NULL';
    }

    /**
     * datetime/varchar 혼재 컬럼을 strict 모드에서도 안전하게 "빈 값이면 NULL"로 만든다.
     * datetime 컬럼을 곧바로 '' 와 비교하면 1525 Incorrect DATETIME value 가 발생하므로
     * 항상 문자열로 캐스팅한 뒤 공백을 제거해 비교한다.
     */
    public static function sqlBlankSafeText(string $qualifiedColumn): string
    {
        return match (Schema::getConnection()->getDriverName()) {
            'sqlite' => "NULLIF(TRIM(CAST({$qualifiedColumn} AS TEXT)), '')",
            default => "NULLIF(TRIM(CAST({$qualifiedColumn} AS CHAR)), '')",
        };
    }

    /**
     * ISO 날짜·엑셀 serial 혼재 컬럼이 특정 연도에 속하는지 여부 (집계용 raw SQL).
     */
    public static function sqlColumnInYear(string $column, int $year): string
    {
        [$minSerial, $maxSerial] = self::serialBoundsForYear($year);

        return "({$column} IS NOT NULL AND ".self::sqlDateValueIsNotBlank($column).' AND ('.self::sqlYearEquals($column, $year)." OR ({$column} >= {$minSerial} AND {$column} <= {$maxSerial})))";
    }

    /**
     * datetime/varchar 혼재 컬럼의 연도 일치 조건.
     *
     * MySQL 에서 datetime 컬럼을 CAST(... AS CHAR) 로 감싸 YEAR() 를 적용하면
     * NULL 이 반환되는 문제가 있어, 연·월 추출은 항상 원본 컬럼에 직접 적용한다.
     * 엑셀 serial(숫자 문자열)은 호출부(sqlColumnInYear)의 serial 범위 분기에서 처리된다.
     */
    public static function sqlYearEquals(string $column, int $year): string
    {
        return match (Schema::getConnection()->getDriverName()) {
            'sqlite' => "(CAST(strftime('%Y', {$column}) AS INTEGER) = {$year})",
            default => "(YEAR({$column}) = {$year})",
        };
    }

    public static function sqlMonthEquals(string $column, int $month): string
    {
        $month = max(1, min(12, $month));

        return match (Schema::getConnection()->getDriverName()) {
            'sqlite' => "(CAST(strftime('%m', {$column}) AS INTEGER) = {$month})",
            default => "(MONTH({$column}) = {$month})",
        };
    }

    /**
     * ISO 날짜·엑셀 serial 혼재 컬럼이 특정 연·월에 속하는지 여부.
     */
    public static function sqlColumnInYearMonth(string $column, ?int $year, int $month): string
    {
        $normalized = self::sqlNormalizedDateColumn($column);
        $parts = [
            "{$column} IS NOT NULL",
            self::sqlDateValueIsNotBlank($column),
            self::sqlMonthEquals($normalized, $month),
        ];

        if ($year !== null) {
            $parts[] = self::sqlYearEquals($normalized, $year);
        }

        return '('.implode(' AND ', $parts).')';
    }

    public static function sqlYearNotEquals(string $column, int $year): string
    {
        return match (Schema::getConnection()->getDriverName()) {
            'sqlite' => "(CAST(strftime('%Y', {$column}) AS INTEGER) != {$year})",
            default => "(YEAR({$column}) != {$year})",
        };
    }

    /**
     * ISO 날짜·엑셀 serial 혼재 컬럼을 SQL DATE 로 정규화한다(정렬·집계용).
     */
    public static function sqlNormalizedDateColumn(string $qualifiedColumn): string
    {
        $epoch = self::EPOCH;
        $min = self::SERIAL_MIN;
        $max = self::SERIAL_MAX;

        // datetime 컬럼을 CAST(... AS CHAR) 로 감싼 뒤 DATE()/YEAR() 를 적용하면
        // MySQL 에서 NULL 이 반환되므로, serial 이 아닌 값은 원본 컬럼에 직접 DATE() 를 적용한다.
        return match (Schema::getConnection()->getDriverName()) {
            'sqlite' => "CASE WHEN {$qualifiedColumn} >= {$min} AND {$qualifiedColumn} <= {$max}"
                ." THEN date('{$epoch}', '+' || CAST({$qualifiedColumn} AS INTEGER) || ' days')"
                ." ELSE date({$qualifiedColumn}) END",
            default => "CASE WHEN {$qualifiedColumn} >= {$min} AND {$qualifiedColumn} <= {$max}"
                ." THEN DATE_ADD('{$epoch}', INTERVAL CAST({$qualifiedColumn} AS UNSIGNED) DAY)"
                ." ELSE DATE({$qualifiedColumn}) END",
        };
    }

    /**
     * Unix 에포크(1970-01-01)로 잘못 캐스팅된 placeholder 여부.
     */
    public static function isEpochArtifact(CarbonInterface $value): bool
    {
        return $value->year === 1970 && $value->month === 1 && $value->day === 1;
    }

    /**
     * @return list<string>
     */
    public static function teacherPlanDateColumns(): array
    {
        return [
            'Plan_1st_Support_Date',
            'Plan_2nd_Support_Date',
            'Plan_3rd_Support_Date',
            'Plan_4th_Support_Date',
        ];
    }

    /**
     * @return list<string>
     */
    public static function teacherSupportDateColumns(): array
    {
        return [
            'Plan_1st_Support_Date',
            'Plan_2nd_Support_Date',
            'Plan_3rd_Support_Date',
            'Plan_4th_Support_Date',
            '_1st_Support_Date',
            '_2nd_Support_Date',
            '_3rd_Support_Date',
            '_4th_Support_Date',
            'GrapeSEEDEssentials',
            'LittleSEEDEssentials',
        ];
    }
}
