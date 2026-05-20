<?php

namespace App\Support;

use App\Models\SupportRecord;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeacherSupportHistoryDetailResolver
{
    /**
     * @return array{title: string, subtitle: string, sections: list<array{title: string, fields: list<array{label: string, value: string}>}>}|null
     */
    public function resolve(string $detailKey, ?int $expectedTeacherId = null, ?string $expectedSkCode = null): ?array
    {
        if (preg_match('/^account:(\d+)$/', $detailKey, $accountMatch)) {
            return $this->resolveAccount((int) $accountMatch[1], $expectedTeacherId, $expectedSkCode);
        }

        $parts = explode(':', $detailKey, 3);
        if (count($parts) !== 3) {
            return null;
        }

        [$source, $tableOrType, $idPart] = $parts;
        $recordId = is_numeric($idPart) ? (int) $idPart : 0;
        if ($recordId <= 0) {
            return null;
        }

        return match ($source) {
            'legacy' => $this->resolveLegacy($tableOrType, $recordId, $expectedTeacherId, $expectedSkCode),
            'mochi' => $this->resolveMochi($tableOrType, $recordId, $expectedTeacherId),
            default => null,
        };
    }

    /**
     * @return array{title: string, subtitle: string, sections: list<array{title: string, fields: list<array{label: string, value: string}>}>}|null
     */
    private function resolveLegacy(string $table, int $recordId, ?int $expectedTeacherId, ?string $expectedSkCode): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $row = DB::table($table)->where('ID', $recordId)->first();
        if (! $row) {
            return null;
        }

        $teacherIdColumn = $this->legacyTeacherIdColumn($table);
        if ($expectedTeacherId !== null && $teacherIdColumn !== null) {
            if ((int) ($row->{$teacherIdColumn} ?? 0) !== $expectedTeacherId) {
                return null;
            }
        }

        if ($expectedTeacherId === null && filled($expectedSkCode) && Schema::hasColumn($table, 'SK_Code')) {
            if (SkCodeNormalizer::normalize($row->SK_Code ?? '') !== SkCodeNormalizer::normalize($expectedSkCode)) {
                return null;
            }
        }

        $typeLabel = $this->legacyTypeLabel($table, $row);

        return [
            'title' => $typeLabel,
            'subtitle' => $this->formatDisplayDate($row->SupportDate ?? null),
            'sections' => [
                [
                    'title' => '보고서 상세',
                    'fields' => $this->legacyFields($row),
                ],
            ],
        ];
    }

    /**
     * @return array{title: string, subtitle: string, sections: list<array{title: string, fields: list<array{label: string, value: string}>}>}|null
     */
    private function resolveAccount(int $recordId, ?int $expectedTeacherId, ?string $expectedSkCode): ?array
    {
        $record = SupportRecord::query()->find($recordId);
        if (! $record) {
            return null;
        }

        if ($expectedTeacherId !== null) {
            $teacher = Teacher::query()->find($expectedTeacherId);
            if ($teacher && ! $this->accountMatchesTeacher($record, $teacher)) {
                return null;
            }
        } elseif (filled($expectedSkCode)) {
            if (SkCodeNormalizer::normalize($record->SK_Code) !== SkCodeNormalizer::normalize($expectedSkCode)) {
                return null;
            }
        }

        $fields = [];
        foreach (config('coach_teacher_support_detail.account_field_labels', []) as $key => $label) {
            $value = match ($key) {
                'tr_name' => $record->TR_Name,
                'support_type' => $record->Support_Type,
                'target' => $record->Target,
                'issue' => $record->Issue,
                'to_account' => $record->TO_Account,
                'to_depart' => $record->TO_Depart,
                'others' => $record->Others,
                'support_date' => $record->Support_Date?->format('Y-m-d'),
                'support_time' => $record->Meet_Time,
                'status' => $record->CompletedDate ? '완료' : '진행중',
                'created_date' => $record->CreatedDate?->format('Y-m-d H:i'),
                'completed_date' => $record->CompletedDate?->format('Y-m-d H:i'),
                default => null,
            };
            $fields[] = ['label' => $label, 'value' => $this->stringify($value)];
        }

        return [
            'title' => (string) ($record->Support_Type ?? '교사 지원'),
            'subtitle' => $record->Support_Date?->format('Y-m-d') ?? '-',
            'sections' => [
                ['title' => '지원 내역', 'fields' => $fields],
            ],
        ];
    }

    /**
     * @return array{title: string, subtitle: string, sections: list<array{title: string, fields: list<array{label: string, value: string}>}>}|null
     */
    private function resolveMochi(string $table, int $recordId, ?int $expectedTeacherId): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $row = DB::table($table)->where('id', $recordId)->first();
        if (! $row) {
            return null;
        }

        if ($expectedTeacherId !== null && (int) ($row->teacher_id ?? 0) !== $expectedTeacherId) {
            return null;
        }

        $typeLabel = config('coach_teacher_legacy_support.mochi_report_tables')[$table] ?? '교사 지원';
        $configKey = config('coach_teacher_support_detail.mochi_config_map')[$table] ?? null;
        $optionConfig = $configKey ? config($configKey, []) : [];

        $fields = [];
        $rowArray = (array) $row;
        $skip = ['id', 'teacher_id', 'support_record_id', 'created_by', 'created_at', 'updated_at'];

        foreach ($rowArray as $column => $value) {
            if (in_array($column, $skip, true)) {
                continue;
            }

            $label = config('coach_teacher_support_detail.mochi_field_labels')[$column]
                ?? str_replace('_', ' ', ucfirst($column));

            $fields[] = [
                'label' => $label,
                'value' => $this->formatMochiValue($column, $value, $optionConfig),
            ];
        }

        return [
            'title' => $typeLabel,
            'subtitle' => $this->formatDisplayDate($row->support_date ?? null),
            'sections' => [
                ['title' => '보고서 상세', 'fields' => array_values(array_filter(
                    $fields,
                    fn (array $field) => $field['value'] !== '-',
                ))],
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function legacyFields(object $row): array
    {
        $fields = [];
        $skip = config('coach_teacher_support_detail.legacy_skip_columns', []);
        $labels = config('coach_teacher_support_detail.legacy_labels', []);
        $handledPairs = [];
        $rowArray = (array) $row;

        foreach ($rowArray as $column => $value) {
            if (in_array($column, $skip, true)) {
                continue;
            }

            if (preg_match('/^(SP|VT|LT|DFT)_(\d+)_(Item|item)$/i', $column, $matches)) {
                $prefix = strtoupper($matches[1]);
                $index = $matches[2];
                $pairKey = "{$prefix}_{$index}";
                if (isset($handledPairs[$pairKey])) {
                    continue;
                }
                $handledPairs[$pairKey] = true;

                $itemCol = "{$prefix}_{$index}_Item";
                $itemColAlt = "{$prefix}_{$index}_item";
                $valueCol = "{$prefix}_{$index}_Value";

                $itemLabel = $rowArray[$itemCol] ?? $rowArray[$itemColAlt] ?? null;
                $itemValue = $rowArray[$valueCol] ?? null;

                if (! filled($itemLabel) && ! filled($itemValue)) {
                    continue;
                }

                $fields[] = [
                    'label' => (string) ($itemLabel ?: "{$prefix} {$index}"),
                    'value' => $this->stringify($itemValue),
                ];

                continue;
            }

            if (preg_match('/^(SP|VT|LT|DFT)_\d+_Value$/i', $column)) {
                continue;
            }

            if (! filled($value) && $value !== 0 && $value !== '0') {
                continue;
            }

            $fields[] = [
                'label' => $labels[$column] ?? $column,
                'value' => $this->stringify($value),
            ];
        }

        return $fields;
    }

    private function legacyTypeLabel(string $table, object $row): string
    {
        foreach (config('coach_teacher_legacy_support.legacy_sources', []) as $source) {
            if (($source['table'] ?? null) !== $table) {
                continue;
            }

            if (($source['type_resolver'] ?? null) === 'lva') {
                $lvaType = $row->LVA_TYPE ?? null;
                if (! filled($lvaType) && isset($row->ReportType)) {
                    $lvaType = config('coach_teacher_legacy_support.lva_report_types')[(int) $row->ReportType] ?? null;
                }

                return filled($lvaType) ? '교사 지원 LVA '.$lvaType : '교사 지원 LVA';
            }

            return (string) ($source['type'] ?? '교사 지원');
        }

        $mochiLabel = config('coach_teacher_legacy_support.mochi_report_tables')[$table] ?? null;

        return $mochiLabel ?? '교사 지원';
    }

    /**
     * @param  array<string, mixed>  $optionConfig
     */
    private function formatMochiValue(string $column, mixed $value, array $optionConfig): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (in_array($column, ['support_date', 'interview_date'], true)) {
            return $this->formatDisplayDate($value);
        }

        if (is_string($value) && $this->looksLikeJson($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return $this->stringify($value);
        }

        $mapKeys = match ($column) {
            'procedures' => $optionConfig['procedures'] ?? [],
            'verbal_tools' => $optionConfig['verbal_tools'] ?? [],
            'language_arts_tools' => $optionConfig['language_arts_tools'] ?? [],
            'evaluations' => $optionConfig['evaluation_criteria'] ?? [],
            'strength_areas', 'growth_areas' => $this->flattenCoachReportMaps($optionConfig),
            default => [],
        };

        if ($column === 'evaluations' && $mapKeys !== []) {
            $scale = $optionConfig['evaluation_scale'] ?? [];
            $lines = [];
            foreach ($value as $key => $score) {
                $criterion = $mapKeys[$key] ?? $key;
                $scaleLabel = $scale[$score] ?? (string) $score;
                $lines[] = "{$criterion}: {$scaleLabel}";
            }

            return implode("\n", $lines);
        }

        if ($mapKeys !== []) {
            $selected = [];
            foreach ($value as $key) {
                if (is_string($key) && isset($mapKeys[$key])) {
                    $selected[] = $mapKeys[$key];
                }
            }

            return $selected !== [] ? implode(', ', $selected) : $this->stringify($value);
        }

        return $this->stringify($value);
    }

    /**
     * @param  array<string, mixed>  $optionConfig
     * @return array<string, string>
     */
    private function flattenCoachReportMaps(array $optionConfig): array
    {
        $flat = [];
        $coachReport = $optionConfig['coach_report'] ?? [];
        foreach ($coachReport as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group['items'] ?? [] as $key => $label) {
                $flat[$key] = $label;
            }
        }

        return $flat;
    }

    private function accountMatchesTeacher(SupportRecord $record, Teacher $teacher): bool
    {
        $target = (string) ($record->Target ?? '');
        $teacherName = (string) $teacher->Name;
        $normalizedTarget = preg_replace('/\s+/u', '', $target) ?? '';
        $normalizedName = preg_replace('/\s+/u', '', $teacherName) ?? '';

        if ($target !== '' && ($target === $teacherName || $normalizedTarget === $normalizedName)) {
            return true;
        }

        $skCode = SkCodeNormalizer::normalize($teacher->SK_Code);

        return filled($skCode) && SkCodeNormalizer::normalize($record->SK_Code) === $skCode;
    }

    private function legacyTeacherIdColumn(string $table): ?string
    {
        foreach (['TeacherId', 'TeacherID'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function formatDisplayDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('n/j/Y H:i:s');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? '예' : '아니오';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '-';
        }

        return (string) $value;
    }

    private function looksLikeJson(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[');
    }
}
