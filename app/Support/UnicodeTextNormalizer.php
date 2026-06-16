<?php

namespace App\Support;

/**
 * macOS 등에서 NFD로 저장된 한글을 NFC로 합쳐 Windows Excel 등에서 읽기 쉽게 한다.
 */
final class UnicodeTextNormalizer
{
    public static function toNfc(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized) && $normalized !== '') {
                return $normalized;
            }
        }

        return $text;
    }
}
