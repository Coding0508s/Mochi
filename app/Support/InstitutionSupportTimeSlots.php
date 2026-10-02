<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * 기관 지원 보고서의 지원 시간. 30분 간격만 새로 고를 수 있다.
 */
final class InstitutionSupportTimeSlots
{
    public const VALIDATION_REGEX = 'regex:/^([01]\d|2[0-3]):(00|30)$/';

    /**
     * @return list<string>
     */
    public static function slots(): array
    {
        $slots = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $slots[] = sprintf('%02d:00', $hour);
            $slots[] = sprintf('%02d:30', $hour);
        }

        return $slots;
    }

    public static function isSlot(string $time): bool
    {
        return in_array($time, self::slots(), true);
    }

    public static function defaultValue(): string
    {
        return self::nearest(now());
    }

    /**
     * 비어 있으면 통과한다. 값이 있으면 30분 칸이거나, 수정 중인 기존 시각이어야 한다.
     *
     * @return list<mixed>
     */
    public static function rules(?string $preserved = null): array
    {
        return [
            'nullable',
            'string',
            'max:10',
            function (string $attribute, mixed $value, \Closure $fail) use ($preserved): void {
                if (self::accepts($value, $preserved)) {
                    return;
                }

                $fail('면담 시간은 30분 단위로 선택해 주세요.');
            },
        ];
    }

    public static function accepts(mixed $value, ?string $preserved = null): bool
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = self::normalize($value);
        $kept = self::normalize($preserved);

        return $normalized !== null && (self::isSlot($normalized) || $normalized === $kept);
    }

    public static function nearest(CarbonInterface $time): string
    {
        $rounded = $time->copy()->seconds(0);
        $minute = (int) $rounded->minute;

        if ($minute < 15) {
            $rounded->minute(0);
        } elseif ($minute < 45) {
            $rounded->minute(30);
        } else {
            $rounded->addHour()->minute(0);
        }

        return $rounded->format('H:i');
    }

    /**
     * 저장된 값이 30분 칸이 아니면 선택 목록에 그 시각을 하나 더 넣는다.
     *
     * @return list<string>
     */
    public static function optionsIncluding(?string $current): array
    {
        $options = self::slots();
        $current = self::normalize($current);

        if ($current !== null && ! in_array($current, $options, true)) {
            $options[] = $current;
            sort($options);
        }

        return $options;
    }

    public static function normalize(?string $value): ?string
    {
        $stringValue = trim((string) $value);

        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $stringValue, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }
}
