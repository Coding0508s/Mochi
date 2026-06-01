<?php

namespace App\Support;

/**
 * 사용자 입력·복붙 텍스트의 줄 단위 앞뒤 공백·전각 공백을 정리한다.
 *
 * whitespace-pre-wrap 조회 시 짧은 제목 줄이 가운데처럼 보이는 현상을 줄이기 위함.
 */
final class MultilineTextNormalizer
{
    public static function normalize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\t", ' ', $text);

        $lines = explode("\n", $text);
        $normalizedLines = array_map(
            static fn (string $line): string => self::normalizeLine($line),
            $lines,
        );

        $result = trim(implode("\n", $normalizedLines));

        return $result === '' ? null : $result;
    }

    private static function normalizeLine(string $line): string
    {
        $line = str_replace("\t", ' ', $line);
        $line = str_replace("\u{3000}", ' ', $line);
        $line = preg_replace('/[\x{FEFF}\x{200B}\x{200C}\x{200D}\x{2060}]/u', '', $line) ?? $line;
        $line = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $line) ?? $line;

        if ($line === '') {
            return '';
        }

        return preg_replace('/[ ]+/u', ' ', $line) ?? $line;
    }
}
