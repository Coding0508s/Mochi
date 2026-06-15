<?php

namespace App\Support;

use Carbon\Carbon;

final class ScheduleTimeInput
{
    public const VALIDATION_REGEX = 'regex:/^(?:([01][0-9]|2[0-3]):[0-5][0-9]|24:00)$/';

    public static function isValid(?string $time): bool
    {
        $time = trim((string) $time);

        if ($time === '') {
            return false;
        }

        return preg_match('/^(?:([01][0-9]|2[0-3]):[0-5][0-9]|24:00)$/', $time) === 1;
    }

    public static function parseOnDate(string $date, string $time): Carbon
    {
        if (trim($time) === '24:00') {
            return Carbon::parse($date)->endOfDay();
        }

        return Carbon::parse($date.' '.$time);
    }
}
