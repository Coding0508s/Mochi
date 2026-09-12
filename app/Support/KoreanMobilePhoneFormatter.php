<?php

namespace App\Support;

final class KoreanMobilePhoneFormatter
{
    /**
     * 숫자만 남겨 010-0000-0000 형태로 맞춥니다. 최대 11자리입니다.
     */
    public static function format(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        $digits = substr($digits, 0, 11);

        if ($digits === '') {
            return '';
        }

        $length = strlen($digits);

        if ($length <= 3) {
            return $digits;
        }

        if ($length <= 7) {
            return substr($digits, 0, 3).'-'.substr($digits, 3);
        }

        return substr($digits, 0, 3).'-'.substr($digits, 3, 4).'-'.substr($digits, 7);
    }
}
