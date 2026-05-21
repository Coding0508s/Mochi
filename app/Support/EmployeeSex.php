<?php

namespace App\Support;

/**
 * HR employee.SEX 컬럼 값 (레거시 DB: NOT NULL, 미지정은 빈 문자열).
 */
final class EmployeeSex
{
    public const UNSPECIFIED = '';

    public const MALE = 'M';

    public const FEMALE = 'F';

    /**
     * @return array<string, string> value => label
     */
    public static function options(): array
    {
        return [
            self::UNSPECIFIED => '미지정',
            self::MALE => '남',
            self::FEMALE => '여',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedValues(): array
    {
        return [self::UNSPECIFIED, self::MALE, self::FEMALE];
    }

    public static function normalizeForStorage(?string $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            self::MALE, self::FEMALE => $normalized,
            default => self::UNSPECIFIED,
        };
    }
}
