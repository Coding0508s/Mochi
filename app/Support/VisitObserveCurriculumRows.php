<?php

namespace App\Support;

final class VisitObserveCurriculumRows
{
    public const TYPE_GRAPESEED = 'GrapeSEED';

    public const TYPE_LITTLESEED = 'LittleSEED';

    public const TYPE_SET = 'SET';

    public const TYPE_DAY = 'Day';

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_GRAPESEED,
            self::TYPE_LITTLESEED,
            self::TYPE_SET,
            self::TYPE_DAY,
        ];
    }

    /**
     * @return list<array{type: string, unit: int|null, lesson: int|null, class: string, age: string}>
     */
    public static function defaultRows(): array
    {
        return [];
    }

    /**
     * @return array{type: string, unit: int|null, lesson: int|null, class: string, age: string}
     */
    public static function emptyRow(string $type): array
    {
        return [
            'type' => $type,
            'unit' => null,
            'lesson' => null,
            'class' => '',
            'age' => '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{type: string, unit: int|null, lesson: int|null, class: string, age: string}>
     */
    public static function normalize(array $rows): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = (string) ($row['type'] ?? '');
            if (! in_array($type, self::types(), true) || in_array($type, $seen, true)) {
                continue;
            }

            $seen[] = $type;
            $normalized[] = [
                'type' => $type,
                'unit' => self::nullableInt($row['unit'] ?? null),
                'lesson' => self::nullableInt($row['lesson'] ?? null),
                'class' => trim((string) ($row['class'] ?? '')),
                'age' => trim((string) ($row['age'] ?? '')),
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    public static function addableTypes(array $rows): array
    {
        $used = array_column(self::normalize($rows), 'type');

        return array_values(array_filter(
            [self::TYPE_GRAPESEED, self::TYPE_LITTLESEED],
            fn (string $type): bool => ! in_array($type, $used, true),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{type: string, unit: int|null, lesson: int|null, class: string, age: string}>
     */
    public static function add(array $rows, string $type): array
    {
        $rows = self::normalize($rows);

        if (! in_array($type, self::addableTypes($rows), true)) {
            return $rows;
        }

        $rows[] = self::emptyRow($type);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{type: string, unit: int|null, lesson: int|null, class: string, age: string}>
     */
    public static function remove(array $rows, int $index): array
    {
        $rows = self::normalize($rows);

        if (! isset($rows[$index])) {
            return $rows;
        }

        unset($rows[$index]);

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function hydrateForm(array $form): array
    {
        $form['observe_rows'] = self::rowsFromForm($form);

        return $form;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyToPayload(array $data): array
    {
        $rows = self::rowsFromForm($data);
        $grape = collect($rows)->firstWhere('type', self::TYPE_GRAPESEED) ?? self::emptyRow(self::TYPE_GRAPESEED);

        $data['observe_rows'] = $rows;
        $data['observe_curriculum_rows'] = $rows;
        $data['observe_unit'] = $grape['unit'];
        $data['observe_lesson'] = $grape['lesson'];
        $data['observe_class'] = $grape['class'] !== '' ? $grape['class'] : null;
        $data['observe_age'] = $grape['age'] !== '' ? $grape['age'] : null;

        return $data;
    }

    /**
     * @return array{unit: string, lesson: string, class: string, age: string}
     */
    public static function fieldLabels(string $type): array
    {
        if ($type === self::TYPE_LITTLESEED) {
            return [
                'unit' => 'SET',
                'lesson' => 'Day',
                'class' => '반',
                'age' => '세',
            ];
        }

        return [
            'unit' => 'Unit',
            'lesson' => 'Lesson',
            'class' => '반',
            'age' => '세',
        ];
    }

    /**
     * @param  list<array<string, mixed>>|string|null  $rows
     */
    public static function formatForDisplay(mixed $rows): string
    {
        if (is_string($rows)) {
            $decoded = json_decode($rows, true);
            $rows = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($rows)) {
            return '';
        }

        $lines = [];

        foreach (self::normalize($rows) as $row) {
            $parts = [];
            $labels = self::fieldLabels($row['type']);
            if ($row['unit'] !== null) {
                $parts[] = $labels['unit'].' '.$row['unit'];
            }
            if ($row['lesson'] !== null) {
                $parts[] = $labels['lesson'].' '.$row['lesson'];
            }
            if ($row['class'] !== '') {
                $parts[] = '반 '.$row['class'];
            }
            if ($row['age'] !== '') {
                $parts[] = '세 '.$row['age'];
            }

            if ($parts === []) {
                continue;
            }

            $lines[] = $row['type'].' '.implode(' / ', $parts);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return list<array{type: string, unit: int|null, lesson: int|null, class: string, age: string}>
     */
    private static function rowsFromForm(array $form): array
    {
        $rows = $form['observe_rows'] ?? $form['observe_curriculum_rows'] ?? null;

        if (is_string($rows)) {
            $decoded = json_decode($rows, true);
            $rows = is_array($decoded) ? $decoded : null;
        }

        $hasRowPayload = array_key_exists('observe_rows', $form)
            || array_key_exists('observe_curriculum_rows', $form);

        if ($hasRowPayload) {
            if (is_array($rows) && $rows !== []) {
                return self::normalize($rows);
            }

            if (is_array($rows)) {
                return [];
            }
        }

        if (self::hasScalarObserveValues($form)) {
            return [self::rowFromScalars($form)];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private static function hasScalarObserveValues(array $form): bool
    {
        return self::nullableInt($form['observe_unit'] ?? null) !== null
            || self::nullableInt($form['observe_lesson'] ?? null) !== null
            || trim((string) ($form['observe_class'] ?? '')) !== ''
            || trim((string) ($form['observe_age'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array{type: string, unit: int|null, lesson: int|null, class: string, age: string}
     */
    private static function rowFromScalars(array $form): array
    {
        $row = self::emptyRow(self::TYPE_GRAPESEED);
        $row['unit'] = self::nullableInt($form['observe_unit'] ?? null);
        $row['lesson'] = self::nullableInt($form['observe_lesson'] ?? null);
        $row['class'] = trim((string) ($form['observe_class'] ?? ''));
        $row['age'] = trim((string) ($form['observe_age'] ?? ''));

        return $row;
    }

    private static function nullableInt(mixed $value): mixed
    {
        $value = NullableFormInteger::toNullableInt($value);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }
}
