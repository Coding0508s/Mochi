<?php

namespace App\Support;

final class VehicleArrivalLocation
{
    /**
     * @return array<int, string>
     */
    public static function floorOptions(): array
    {
        return ['B1', 'B2', 'B3'];
    }

    /**
     * @return array<int, string>
     */
    public static function pillarOptions(): array
    {
        return ['A', 'B'];
    }

    /**
     * @return array{floor: string, pillar: string, number: string}
     */
    public static function parse(?string $value): array
    {
        $text = trim((string) $value);
        if ($text === '') {
            return self::emptyParts();
        }

        if (preg_match('/^(B[1-3])\s*[\/\s]\s*([AB])(\d{1,3})\s*$/u', $text, $matches) === 1) {
            return self::normalizedParts($matches[1], $matches[2], $matches[3]);
        }

        if (preg_match('/^(B[1-3])\s+([AB])(\d{1,3})\s*$/u', $text, $matches) === 1) {
            return self::normalizedParts($matches[1], $matches[2], $matches[3]);
        }

        return self::emptyParts();
    }

    public static function compose(string $floor, string $pillar, string $number): string
    {
        $floor = trim($floor);
        $pillar = trim($pillar);
        $number = trim($number);

        if ($floor === '' || $pillar === '' || $number === '') {
            return '';
        }

        return $floor.' / '.$pillar.$number;
    }

    public static function forDisplay(?string $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $parts = self::parse($text);
        if ($parts['floor'] !== '') {
            return self::compose($parts['floor'], $parts['pillar'], $parts['number']);
        }

        return $text;
    }

    public static function isStructured(?string $value): bool
    {
        $parts = self::parse($value);

        return $parts['floor'] !== ''
            && $parts['pillar'] !== ''
            && $parts['number'] !== '';
    }

    /**
     * @return array{floor: string, pillar: string, number: string}
     */
    private static function normalizedParts(string $floor, string $pillar, string $number): array
    {
        $floor = strtoupper(trim($floor));
        $pillar = strtoupper(trim($pillar));
        $numberValue = (int) trim($number);

        if (! in_array($floor, self::floorOptions(), true)
            || ! in_array($pillar, self::pillarOptions(), true)
            || $numberValue < 1
            || $numberValue > 100) {
            return self::emptyParts();
        }

        return [
            'floor' => $floor,
            'pillar' => $pillar,
            'number' => (string) $numberValue,
        ];
    }

    /**
     * @return array{floor: string, pillar: string, number: string}
     */
    private static function emptyParts(): array
    {
        return [
            'floor' => '',
            'pillar' => '',
            'number' => '',
        ];
    }
}
