<?php

namespace App\Support;

final class VehicleUsageLogRemark
{
    public static function forDisplay(?string $remarks): string
    {
        $text = trim((string) $remarks);
        if ($text === '') {
            return '';
        }

        return trim((string) preg_replace('/\[excel-schedule:[^\]]+\]\s*/u', '', $text));
    }

    public static function combineArrivalAndReason(?string $arrivalLocation, ?string $reason): string
    {
        $arrival = trim((string) $arrivalLocation);
        $reasonText = trim((string) $reason);

        if ($arrival !== '' && $reasonText !== '') {
            return $arrival.' / '.$reasonText;
        }

        if ($arrival !== '') {
            return $arrival;
        }

        return $reasonText;
    }
}
