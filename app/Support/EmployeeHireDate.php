<?php

namespace App\Support;

/**
 * HR employee.HIREDATE 컬럼 값.
 *
 * 운영 MySQL은 NOT NULL + strict mode(NO_ZERO_DATE)라 0000-00-00을 넣을 수 없습니다.
 * 미지정 입사일은 유효한 placeholder(1970-01-01)로 저장하고, 화면에서는 '-'로 표시합니다.
 * 레거시 0000-00-00 값도 읽기 시 미지정으로 취급합니다.
 */
final class EmployeeHireDate
{
    /** MySQL strict mode에서 허용되는 미지정 placeholder (Unix epoch). */
    public const UNSPECIFIED = '1970-01-01';

    public static function defaultForStorage(): string
    {
        return self::UNSPECIFIED;
    }

    public static function isUnspecified(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || $trimmed === '0000-00-00' || str_starts_with($trimmed, '0000-00-00')) {
                return true;
            }
        }

        $parsed = ExcelSerialDate::parse($value);

        return $parsed === null || ExcelSerialDate::isEpochArtifact($parsed);
    }

    public static function formatDisplay(mixed $value): string
    {
        if (self::isUnspecified($value)) {
            return '-';
        }

        $parsed = ExcelSerialDate::parse($value);

        return $parsed === null ? '-' : $parsed->format('Y-m-d');
    }
}
