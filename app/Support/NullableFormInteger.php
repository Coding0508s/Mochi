<?php

namespace App\Support;

class NullableFormInteger
{
    /**
     * @var list<string>
     */
    private const NORMALIZABLE_KEYS = [
        'observe_unit',
        'observe_lesson',
        'session_number',
        'support_round',
        'progress_unit',
        'progress_lesson',
        'video_length_minutes',
        'observe_day',
        'lesson_length_minutes',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $data): array
    {
        return self::normalize($data, self::NORMALIZABLE_KEYS);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function normalize(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $data[$key] = self::toNullableInt($data[$key]);
        }

        return $data;
    }

    public static function toNullableInt(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
