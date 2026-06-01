<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class LegacyTeacherSupportReportPersister
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function build(string $table, string $action, array $validated, string $status, object $existingRow): array
    {
        $updates = self::baseFields($table, $validated, $status);

        $updates = array_merge($updates, match ($action) {
            'demo_lesson' => self::demoLessonFields($table, $validated, $existingRow),
            'lva_fr', 'lva_fb' => self::lvaFields($table, $validated, $existingRow, $action),
            'onsite' => self::onsiteFields($table, $validated, $existingRow),
            'open_class', 'ls_onsite_lva', 'unit21_plus', 'unit31_plus', 'pro_con' => self::sessionStyleFields(
                $table,
                $validated,
                $existingRow,
                $action,
            ),
            default => [],
        });

        if (Schema::hasColumn($table, 'ModifyDate')) {
            $updates['ModifyDate'] = now();
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private static function baseFields(string $table, array $validated, string $status): array
    {
        return self::onlyExistingColumns($table, [
            'SK_Code' => $validated['sk_code'],
            'TR_Name' => $validated['coach_name'],
            'Account_Name' => $validated['institution_name'],
            'Teacher' => $validated['teacher_name'],
            'SupportDate' => $validated['support_date'],
            'Status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private static function demoLessonFields(string $table, array $validated, object $existingRow): array
    {
        $config = config('coach_teacher_demo_lesson', []);

        $updates = self::onlyExistingColumns($table, [
            'Unit1' => $validated['progress_unit'] ?? null,
            'Lesson1' => $validated['progress_lesson'] ?? null,
            'Other' => $validated['other_notes'] ?? null,
            'Comments1' => $validated['comments_primary'] ?? null,
            'Comments2' => $validated['comments_secondary'] ?? null,
            'Overall_Comments' => $validated['overall_comments'] ?? null,
        ]);

        self::writeCheckedKeys(
            $updates,
            $table,
            $existingRow,
            'SP',
            $config['procedures'] ?? [],
            $validated['procedures'] ?? [],
        );
        self::writeCheckedKeys(
            $updates,
            $table,
            $existingRow,
            'VT',
            $config['verbal_tools'] ?? [],
            $validated['verbal_tools'] ?? [],
        );
        self::writeCheckedKeys(
            $updates,
            $table,
            $existingRow,
            'LT',
            $config['language_arts_tools'] ?? [],
            $validated['language_arts_tools'] ?? [],
        );
        self::writeEvaluationScores(
            $updates,
            $table,
            $existingRow,
            'DFT',
            $config['evaluation_criteria'] ?? [],
            $validated['evaluations'] ?? [],
        );

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private static function lvaFields(string $table, array $validated, object $existingRow, string $action): array
    {
        $configKey = $action === 'lva_fr' ? 'coach_teacher_lva_fr' : 'coach_teacher_lva_fb';
        $config = config($configKey, []);

        $updates = array_merge(
            self::observationFields($table, $validated),
            self::sessionMetaFields($table, $validated),
            self::onlyExistingColumns($table, [
                'Other' => $validated['other_notes'] ?? null,
                'FileLength' => $validated['video_length_minutes'] ?? null,
            ]),
        );

        self::writeCheckedKeys(
            $updates,
            $table,
            $existingRow,
            'SP',
            $config['procedures'] ?? [],
            $validated['procedures'] ?? [],
        );
        self::writeCoachReportAreas(
            $updates,
            $table,
            $existingRow,
            $config,
            $validated['strength_areas'] ?? [],
            $validated['growth_areas'] ?? [],
        );

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private static function onsiteFields(string $table, array $validated, object $existingRow): array
    {
        $config = config('coach_teacher_onsite', []);

        $otherNotes = filled($validated['other_notes'] ?? null)
            ? (string) $validated['other_notes']
            : (string) ($validated['observe_summary_extra'] ?? '');

        $updates = array_merge(
            self::observationFields($table, $validated),
            self::sessionMetaFields($table, $validated),
            self::onlyExistingColumns($table, [
                'Other' => $otherNotes !== '' ? $otherNotes : null,
            ]),
        );

        self::writeCheckedKeys(
            $updates,
            $table,
            $existingRow,
            'SP',
            $config['procedures'] ?? [],
            $validated['procedures'] ?? [],
        );
        self::writeCoachReportAreas(
            $updates,
            $table,
            $existingRow,
            $config,
            $validated['strength_areas'] ?? [],
            $validated['growth_areas'] ?? [],
        );

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private static function sessionStyleFields(string $table, array $validated, object $existingRow, string $action): array
    {
        $configKey = match ($action) {
            'open_class' => 'coach_teacher_open_class',
            'ls_onsite_lva' => 'coach_teacher_ls_onsite_lva',
            'unit21_plus' => 'coach_teacher_unit21_plus',
            'unit31_plus' => 'coach_teacher_unit31_plus',
            'pro_con' => 'coach_teacher_pro_con',
            default => null,
        };

        if ($configKey === null) {
            return [];
        }

        $config = config($configKey, []);
        $remarks = filled($validated['remarks'] ?? null)
            ? (string) $validated['remarks']
            : (string) ($validated['overall_comments'] ?? '');

        $updates = array_merge(
            self::sessionMetaFields($table, $validated),
            self::onlyExistingColumns($table, [
                'Unit' => $validated['progress_unit'] ?? null,
                'Lesson' => $validated['progress_lesson'] ?? null,
                'Other' => $validated['progress_other'] ?? null,
                'Overall_Comments' => $remarks !== '' ? $remarks : null,
                'Comments1' => $remarks !== '' ? $remarks : null,
            ]),
        );

        self::writeCheckedKeys(
            $updates,
            $table,
            $existingRow,
            'SP',
            $config['procedures'] ?? [],
            $validated['procedures'] ?? [],
        );

        if (isset($config['support_content'])) {
            self::writeCheckedKeys(
                $updates,
                $table,
                $existingRow,
                'SC',
                $config['support_content'],
                $validated['support_content'] ?? [],
            );
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private static function observationFields(string $table, array $validated): array
    {
        return self::onlyExistingColumns($table, [
            'Unit' => $validated['observe_unit'] ?? null,
            'Lesson' => $validated['observe_lesson'] ?? null,
            'ClassName' => $validated['observe_class'] ?? null,
            'Age' => $validated['observe_age'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private static function sessionMetaFields(string $table, array $validated): array
    {
        $semesterLabel = (string) ($validated['semester_label'] ?? '');

        return self::onlyExistingColumns($table, [
            'Career' => $validated['teacher_experience'] ?? null,
            'ChaSu' => $validated['session_number'] ?? null,
            'Haggi' => str_contains($semesterLabel, '2') ? 2 : 1,
            'MeetingDate' => $validated['interview_date'] ?? null,
            'MeetingTime' => self::formatLegacyMeetTime($validated['interview_time'] ?? null),
            'MeetingType' => $validated['method'] ?? null,
        ]);
    }

    /**
     * @param  array<string, string>  $configMap
     * @param  list<string>  $selectedKeys
     * @param  array<string, mixed>  $updates
     */
    private static function writeCheckedKeys(
        array &$updates,
        string $table,
        object $existingRow,
        string $prefix,
        array $configMap,
        array $selectedKeys,
    ): void {
        $rowArray = (array) $existingRow;

        for ($i = 1; $i <= 20; $i++) {
            $itemCol = "{$prefix}_{$i}_Item";
            if (! Schema::hasColumn($table, $itemCol)) {
                break;
            }

            $label = (string) ($rowArray[$itemCol] ?? '');
            if (! filled($label)) {
                continue;
            }

            $key = self::matchConfigKeyByLabel($label, $configMap);
            $valueCol = "{$prefix}_{$i}_Value";
            if (! Schema::hasColumn($table, $valueCol)) {
                continue;
            }

            $updates[$valueCol] = ($key !== null && in_array($key, $selectedKeys, true)) ? 1 : 0;
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $strengthAreas
     * @param  list<string>  $growthAreas
     * @param  array<string, mixed>  $updates
     */
    private static function writeCoachReportAreas(
        array &$updates,
        string $table,
        object $existingRow,
        array $config,
        array $strengthAreas,
        array $growthAreas,
    ): void {
        $prefixMap = [
            'TC' => 'teaching_components',
            'D' => 'delivery',
            'A' => 'advanced',
        ];

        $rowArray = (array) $existingRow;

        foreach ($prefixMap as $prefix => $sectionKey) {
            $sectionItems = $config['coach_report'][$sectionKey]['items'] ?? [];

            for ($i = 1; $i <= 20; $i++) {
                $itemCol = "{$prefix}_{$i}_Item";
                if (! Schema::hasColumn($table, $itemCol)) {
                    break;
                }

                $label = (string) ($rowArray[$itemCol] ?? '');
                if (! filled($label)) {
                    continue;
                }

                $key = self::matchConfigKeyByLabel($label, $sectionItems);
                if ($key === null) {
                    continue;
                }

                $strengthCol = "{$prefix}_{$i}_SA";
                $growthCol = "{$prefix}_{$i}_GA";

                if (Schema::hasColumn($table, $strengthCol)) {
                    $updates[$strengthCol] = in_array($key, $strengthAreas, true) ? 1 : 0;
                }

                if (Schema::hasColumn($table, $growthCol)) {
                    $updates[$growthCol] = in_array($key, $growthAreas, true) ? 1 : 0;
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $configMap
     * @param  array<string, int>  $scores
     * @param  array<string, mixed>  $updates
     */
    private static function writeEvaluationScores(
        array &$updates,
        string $table,
        object $existingRow,
        string $prefix,
        array $configMap,
        array $scores,
    ): void {
        $rowArray = (array) $existingRow;

        for ($i = 1; $i <= 20; $i++) {
            $itemCol = "{$prefix}_{$i}_Item";
            $valueCol = "{$prefix}_{$i}_Value";
            if (! Schema::hasColumn($table, $itemCol) || ! Schema::hasColumn($table, $valueCol)) {
                break;
            }

            $label = (string) ($rowArray[$itemCol] ?? '');
            if (! filled($label)) {
                continue;
            }

            $key = self::matchConfigKeyByLabel($label, $configMap);
            if ($key === null || ! array_key_exists($key, $scores)) {
                continue;
            }

            $updates[$valueCol] = (int) $scores[$key];
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function onlyExistingColumns(string $table, array $fields): array
    {
        $updates = [];

        foreach ($fields as $column => $value) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $updates[$column] = $value;
        }

        return $updates;
    }

    /**
     * @param  array<string, string>  $configMap
     */
    private static function matchConfigKeyByLabel(string $label, array $configMap): ?string
    {
        $normalizedLabel = trim($label);

        foreach ($configMap as $key => $configLabel) {
            if (trim((string) $configLabel) === $normalizedLabel) {
                return $key;
            }
        }

        return null;
    }

    private static function formatLegacyMeetTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = (string) $value;

        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
