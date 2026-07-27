<?php

namespace App\Enums;

enum TeacherEmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Unspecified = 'unspecified';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Full Time',
            self::PartTime => 'Part Time',
            self::Unspecified => '미지정',
        };
    }

    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(trim((string) $value)) ?? self::Unspecified;
    }
}
