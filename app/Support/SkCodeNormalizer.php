<?php

namespace App\Support;

class SkCodeNormalizer
{
    /**
     * Remove leading '*' from an SK code and return null if blank.
     */
    public static function normalize(?string $skCode): ?string
    {
        if (blank($skCode)) {
            return null;
        }

        $normalized = ltrim(trim((string) $skCode), '*');

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Return candidate SK codes for flexible whereIn matching.
     *
     * @return string[]
     */
    public static function candidates(string $skCode): array
    {
        $normalized = self::normalize($skCode);

        if ($normalized === null) {
            return [];
        }

        return array_values(array_unique([
            $skCode,
            $normalized,
            '*'.$normalized,
        ]));
    }
}
