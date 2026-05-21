<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

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

    public static function formatPlanMonth(?CarbonInterface $value): string
    {
        $parsed = self::parse($value);

        return $parsed === null ? '' : $parsed->format('Y년 n월');
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
            '_1st_Support_Date',
            '_2nd_Support_Date',
            '_3rd_Support_Date',
            '_4th_Support_Date',
            'GrapeSEEDEssentials',
            'LittleSEEDEssentials',
        ];
    }
}
