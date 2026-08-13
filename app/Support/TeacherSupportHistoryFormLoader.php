<?php

namespace App\Support;

use App\Models\SupportRecord;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeacherSupportHistoryFormLoader
{
    /**
     * @return array{action: string, teacher_id: int, form: array<string, mixed>, mark_completed: bool}|null
     */
    public function load(string $detailKey, ?int $expectedTeacherId = null, ?string $expectedSkCode = null): ?array
    {
        if (preg_match('/^account:(\d+)$/', $detailKey, $accountMatch)) {
            return $this->loadAccount((int) $accountMatch[1], $expectedTeacherId, $expectedSkCode);
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
            'legacy' => $this->loadLegacy($tableOrType, $recordId, $expectedTeacherId, $expectedSkCode),
            'mochi' => $this->loadMochi($tableOrType, $recordId, $expectedTeacherId),
            default => null,
        };
    }

    /**
     * @return array{action: string, teacher_id: int, form: array<string, mixed>, mark_completed: bool}|null
     */
    private function loadLegacy(string $table, int $recordId, ?int $expectedTeacherId, ?string $expectedSkCode): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $row = DB::table($table)->where('ID', $recordId)->first();
        if (! $row) {
            return null;
        }

        $teacherIdColumn = $this->legacyTeacherIdColumn($table);
        $teacherId = $teacherIdColumn ? (int) ($row->{$teacherIdColumn} ?? 0) : 0;

        if ($expectedTeacherId !== null && $teacherId > 0 && $teacherId !== $expectedTeacherId) {
            return null;
        }

        if ($expectedTeacherId === null && filled($expectedSkCode) && Schema::hasColumn($table, 'SK_Code')) {
            if (SkCodeNormalizer::normalize($row->SK_Code ?? '') !== SkCodeNormalizer::normalize($expectedSkCode)) {
                return null;
            }
        }

        if ($teacherId <= 0) {
            return null;
        }

        $action = $this->resolveLegacyAction($table, $row);
        if ($action === null) {
            return null;
        }

        $form = match ($action) {
            'demo_lesson' => $this->mapLegacyDemoLesson($row),
            'lva_fr', 'lva_fb' => $this->mapLegacyLva($row, $action),
            'onsite' => $this->mapLegacyOnsite($row),
            'open_class' => $this->mapLegacySessionStyle($row, 'coach_teacher_open_class'),
            'ls_onsite_lva' => $this->mapLegacySessionStyle($row, 'coach_teacher_ls_onsite_lva'),
            'unit21_plus' => $this->mapLegacySessionStyle($row, 'coach_teacher_unit21_plus'),
            'unit31_plus' => $this->mapLegacySessionStyle($row, 'coach_teacher_unit31_plus'),
            'pro_con' => $this->mapLegacySessionStyle($row, 'coach_teacher_pro_con'),
            'littleseed_con' => $this->mapLegacySessionStyle($row, 'coach_teacher_littleseed_con'),
            default => null,
        };

        if ($form === null) {
            return null;
        }

        return [
            'action' => $action,
            'teacher_id' => $teacherId,
            'form' => $form,
            'mark_completed' => ($row->Status ?? '완료') === '완료',
        ];
    }

    /**
     * @return array{action: string, teacher_id: int, form: array<string, mixed>, mark_completed: bool}|null
     */
    private function loadMochi(string $table, int $recordId, ?int $expectedTeacherId): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $action = config('coach_teacher_support_history_modal.mochi_table_actions')[$table] ?? null;
        if ($action === null) {
            return null;
        }

        $row = DB::table($table)->where('id', $recordId)->first();
        if (! $row) {
            return null;
        }

        $teacherId = (int) ($row->teacher_id ?? 0);
        if ($teacherId <= 0) {
            return null;
        }

        if ($expectedTeacherId !== null && $teacherId !== $expectedTeacherId) {
            return null;
        }

        $form = (array) $row;
        unset($form['id'], $form['support_record_id'], $form['created_by'], $form['created_at'], $form['updated_at']);

        foreach (['support_date', 'interview_date'] as $dateField) {
            if (isset($form[$dateField])) {
                $form[$dateField] = $this->formatDateInput($form[$dateField]);
            }
        }

        foreach (['procedures', 'verbal_tools', 'language_arts_tools', 'evaluations', 'strength_areas', 'growth_areas', 'support_content'] as $jsonField) {
            if (isset($form[$jsonField]) && is_string($form[$jsonField])) {
                $decoded = json_decode($form[$jsonField], true);
                $form[$jsonField] = is_array($decoded) ? $decoded : [];
            }
        }

        return [
            'action' => $action,
            'teacher_id' => $teacherId,
            'form' => $form,
            'mark_completed' => ($row->status ?? '임시') === '완료',
        ];
    }

    /**
     * @return array{action: string, teacher_id: int, form: array<string, mixed>, mark_completed: bool}|null
     */
    private function loadAccount(int $recordId, ?int $expectedTeacherId, ?string $expectedSkCode): ?array
    {
        $record = SupportRecord::query()->find($recordId);
        if (! $record) {
            return null;
        }

        $teacher = null;
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

        if ($teacher === null && filled($record->Target)) {
            $teacher = Teacher::query()
                ->where('Name', $record->Target)
                ->when(filled($record->SK_Code), fn ($q) => $q->whereIn('SK_Code', SkCodeNormalizer::candidates($record->SK_Code)))
                ->first();
        }

        if (! $teacher) {
            return null;
        }

        $action = config('coach_teacher_support_history_modal.support_type_actions')[$record->Support_Type ?? ''] ?? null;
        if ($action === null) {
            return null;
        }

        $form = $this->mapAccountToForm($record, $teacher, $action);

        return [
            'action' => $action,
            'teacher_id' => (int) $teacher->ID,
            'form' => $form,
            'mark_completed' => $record->isCompleted(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLegacyDemoLesson(object $row): array
    {
        $config = config('coach_teacher_demo_lesson', []);

        $evaluations = [];
        foreach (array_keys($config['evaluation_criteria'] ?? []) as $key) {
            $evaluations[$key] = 2;
        }
        foreach ($this->legacyEvaluationPairs($row, 'DFT') as $label => $score) {
            $key = $this->matchConfigKeyByLabel($label, $config['evaluation_criteria'] ?? []);
            if ($key !== null) {
                $evaluations[$key] = (int) $score;
            }
        }

        return [
            'sk_code' => (string) ($row->SK_Code ?? ''),
            'coach_name' => (string) ($row->TR_Name ?? ''),
            'institution_name' => (string) ($row->Account_Name ?? ''),
            'teacher_name' => (string) ($row->Teacher ?? ''),
            'support_date' => $this->formatDateInput($row->SupportDate ?? null),
            'progress_unit' => filled($row->Unit1 ?? null) ? (int) $row->Unit1 : null,
            'progress_lesson' => filled($row->Lesson1 ?? null) ? (int) $row->Lesson1 : null,
            'other_notes' => (string) ($row->Other ?? ''),
            'procedures' => $this->legacyCheckedKeys($row, 'SP', $config['procedures'] ?? []),
            'verbal_tools' => $this->legacyCheckedKeys($row, 'VT', $config['verbal_tools'] ?? []),
            'language_arts_tools' => $this->legacyCheckedKeys($row, 'LT', $config['language_arts_tools'] ?? []),
            'comments_primary' => (string) ($row->Comments1 ?? ''),
            'comments_secondary' => (string) ($row->Comments2 ?? ''),
            'evaluations' => $evaluations,
            'overall_comments' => (string) ($row->Overall_Comments ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLegacyLva(object $row, string $action): array
    {
        $configKey = $action === 'lva_fr' ? 'coach_teacher_lva_fr' : 'coach_teacher_lva_fb';
        $config = config($configKey, []);

        [$strengthAreas, $growthAreas] = $this->legacyCoachReportAreas($row, $config);

        return [
            'sk_code' => (string) ($row->SK_Code ?? ''),
            'coach_name' => (string) ($row->TR_Name ?? ''),
            'institution_name' => (string) ($row->Account_Name ?? ''),
            'teacher_name' => (string) ($row->Teacher ?? ''),
            'support_date' => $this->formatDateInput($row->SupportDate ?? null),
            'observe_unit' => filled($row->Unit ?? null) ? (int) $row->Unit : null,
            'observe_lesson' => filled($row->Lesson ?? null) ? (int) $row->Lesson : null,
            'observe_class' => (string) ($row->ClassName ?? ''),
            'observe_age' => (string) ($row->Age ?? ''),
            'teacher_experience' => (string) ($row->Career ?? '1-2 Years'),
            'session_number' => filled($row->ChaSu ?? null) ? (int) $row->ChaSu : 1,
            'semester_label' => ((int) ($row->Haggi ?? 1)) === 2 ? '2학기 지원' : '1학기 지원',
            'interview_date' => $this->formatDateInput($row->MeetingDate ?? $row->SupportDate ?? null),
            'interview_time' => $this->formatTimeInput($row->MeetingTime ?? null),
            'method' => (string) ($row->MeetingType ?? '화상'),
            'other_notes' => (string) ($row->Other ?? ''),
            'video_length_minutes' => filled($row->FileLength ?? null) ? (int) $row->FileLength : null,
            'procedures' => $this->legacyCheckedKeys($row, 'SP', $config['procedures'] ?? []),
            'strength_areas' => $strengthAreas,
            'growth_areas' => $growthAreas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLegacyOnsite(object $row): array
    {
        $config = config('coach_teacher_onsite', []);

        [$strengthAreas, $growthAreas] = $this->legacyCoachReportAreas($row, $config);

        return [
            'sk_code' => (string) ($row->SK_Code ?? ''),
            'coach_name' => (string) ($row->TR_Name ?? ''),
            'institution_name' => (string) ($row->Account_Name ?? ''),
            'teacher_name' => (string) ($row->Teacher ?? ''),
            'support_date' => $this->formatDateInput($row->SupportDate ?? null),
            'observe_unit' => filled($row->Unit ?? null) ? (int) $row->Unit : null,
            'observe_lesson' => filled($row->Lesson ?? null) ? (int) $row->Lesson : null,
            'observe_summary_extra' => (string) ($row->Other ?? ''),
            'observe_class' => (string) ($row->ClassName ?? ''),
            'observe_age' => (string) ($row->Age ?? ''),
            'teacher_experience' => (string) ($row->Career ?? '1-2 Years'),
            'session_number' => filled($row->ChaSu ?? null) ? (int) $row->ChaSu : 1,
            'semester_label' => ((int) ($row->Haggi ?? 1)) === 2 ? '2학기 지원' : '1학기 지원',
            'interview_date' => $this->formatDateInput($row->MeetingDate ?? $row->SupportDate ?? null),
            'interview_time' => $this->formatTimeInput($row->MeetingTime ?? null),
            'method' => (string) ($row->MeetingType ?? '대면'),
            'other_notes' => (string) ($row->Other ?? ''),
            'procedures' => $this->legacyCheckedKeys($row, 'SP', $config['procedures'] ?? []),
            'strength_areas' => $strengthAreas,
            'growth_areas' => $growthAreas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLegacySessionStyle(object $row, string $configKey): array
    {
        $config = config($configKey, []);

        return [
            'sk_code' => (string) ($row->SK_Code ?? ''),
            'coach_name' => (string) ($row->TR_Name ?? ''),
            'institution_name' => (string) ($row->Account_Name ?? ''),
            'teacher_name' => (string) ($row->Teacher ?? ''),
            'support_date' => $this->formatDateInput($row->SupportDate ?? null),
            'teacher_experience' => (string) ($row->Career ?? '1-2 Years'),
            'session_number' => filled($row->ChaSu ?? null) ? (int) $row->ChaSu : 1,
            'semester_label' => ((int) ($row->Haggi ?? 1)) === 2 ? '2학기 지원' : '1학기 지원',
            'interview_date' => $this->formatDateInput($row->MeetingDate ?? $row->SupportDate ?? null),
            'interview_time' => $this->formatTimeInput($row->MeetingTime ?? null),
            'method' => (string) ($row->MeetingType ?? '화상'),
            'progress_unit' => filled($row->Unit ?? null) ? (int) $row->Unit : null,
            'progress_lesson' => filled($row->Lesson ?? null) ? (int) $row->Lesson : null,
            'progress_other' => (string) ($row->Other ?? ''),
            'procedures' => $this->legacyCheckedKeys($row, 'SP', $config['procedures'] ?? []),
            'support_content' => $this->legacyCheckedKeys($row, 'SC', $config['support_content'] ?? []),
            'remarks' => (string) ($row->Overall_Comments ?? $row->Comments1 ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAccountToForm(SupportRecord $record, Teacher $teacher, string $action): array
    {
        $base = [
            'sk_code' => SkCodeNormalizer::normalize($teacher->SK_Code) ?? (string) ($record->SK_Code ?? ''),
            'coach_name' => (string) ($record->TR_Name ?? ''),
            'institution_name' => (string) ($record->Account_Name ?? $teacher->School_Name ?? ''),
            'teacher_name' => (string) ($teacher->Name ?? $record->Target ?? ''),
            'support_date' => $record->Support_Date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'other_notes' => (string) ($record->Others ?? ''),
            'remarks' => (string) ($record->Issue ?? ''),
        ];

        if (in_array($action, ['lva_fr', 'lva_fb', 'onsite'], true)) {
            return array_merge($base, [
                'observe_unit' => null,
                'observe_lesson' => null,
                'observe_class' => '',
                'observe_age' => '',
                'teacher_experience' => '1-2 Years',
                'session_number' => 1,
                'semester_label' => '1학기 지원',
                'interview_date' => $base['support_date'],
                'interview_time' => $this->formatTimeInput($record->Meet_Time),
                'method' => (string) ($record->Support_Type ?? '화상'),
                'procedures' => [],
                'strength_areas' => [],
                'growth_areas' => [],
            ]);
        }

        if ($action === 'demo_lesson') {
            $evaluations = [];
            foreach (array_keys(config('coach_teacher_demo_lesson.evaluation_criteria', [])) as $key) {
                $evaluations[$key] = 2;
            }

            return array_merge($base, [
                'progress_unit' => null,
                'progress_lesson' => null,
                'procedures' => [],
                'verbal_tools' => [],
                'language_arts_tools' => [],
                'comments_primary' => '',
                'comments_secondary' => '',
                'evaluations' => $evaluations,
                'overall_comments' => (string) ($record->Issue ?? ''),
            ]);
        }

        if ($action === 'visit') {
            return array_merge($base, [
                'support_location' => '',
                'support_purpose' => '',
                'observe_unit' => null,
                'observe_lesson' => null,
                'observe_summary_extra' => '',
                'observe_class' => '',
                'observe_age' => '',
                // 기관 지원 행에는 차수 정보가 없으므로 「기록 안 함」으로 연다.
                'session_number' => null,
                'semester_label' => '1학기 지원',
                'interview_date' => $base['support_date'],
                'interview_time' => $this->formatTimeInput($record->Meet_Time),
                'meeting_type' => (string) ($record->Support_Type ?? config('coach_teacher_visit.method_options.0', '신규교사 시연수업')),
                'pre_request_notes' => '',
                'monitoring_feedback' => (string) ($record->Issue ?? ''),
                'interview_and_action_plan' => '',
                'special_notes' => '',
            ]);
        }

        return array_merge($base, [
            'teacher_experience' => '1-2 Years',
            'session_number' => 1,
            'semester_label' => '1학기 지원',
            'interview_date' => $base['support_date'],
            'interview_time' => $this->formatTimeInput($record->Meet_Time),
            'method' => (string) ($record->Support_Type ?? '화상'),
            'progress_unit' => null,
            'progress_lesson' => null,
            'progress_other' => '',
            'procedures' => [],
            'support_content' => [],
        ]);
    }

    private function resolveLegacyAction(string $table, object $row): ?string
    {
        if ($table === 'S_Support_LVA') {
            $lvaType = $row->LVA_TYPE ?? null;
            if (! filled($lvaType) && isset($row->ReportType)) {
                $lvaType = config('coach_teacher_legacy_support.lva_report_types')[(int) $row->ReportType] ?? null;
            }
            if ($lvaType === 'FR') {
                return 'lva_fr';
            }
            if ($lvaType === 'FB') {
                return 'lva_fb';
            }

            return config('coach_teacher_support_history_modal.lva_report_type_actions')[(int) ($row->ReportType ?? 0)] ?? null;
        }

        return config('coach_teacher_support_history_modal.legacy_table_actions')[$table] ?? null;
    }

    /**
     * @param  array<string, string>  $configMap
     * @return list<string>
     */
    private function legacyCheckedKeys(object $row, string $prefix, array $configMap): array
    {
        $keys = [];
        $rowArray = (array) $row;

        for ($i = 1; $i <= 20; $i++) {
            $itemCol = "{$prefix}_{$i}_Item";
            if (! array_key_exists($itemCol, $rowArray)) {
                break;
            }

            $label = (string) ($rowArray[$itemCol] ?? '');
            $value = $rowArray["{$prefix}_{$i}_Value"] ?? null;

            if (! filled($label) || ! $this->isLegacyChecked($value)) {
                continue;
            }

            $key = $this->matchConfigKeyByLabel($label, $configMap);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function legacyCoachReportAreas(object $row, array $config): array
    {
        $flatMap = [];
        foreach ($config['coach_report'] ?? [] as $section) {
            foreach ($section['items'] ?? [] as $key => $label) {
                $flatMap[$key] = $label;
            }
        }

        $prefixMap = [
            'TC' => 'teaching_components',
            'D' => 'delivery',
            'A' => 'advanced',
        ];

        $strength = [];
        $growth = [];
        $rowArray = (array) $row;

        foreach ($prefixMap as $prefix => $sectionKey) {
            $sectionItems = $config['coach_report'][$sectionKey]['items'] ?? [];
            for ($i = 1; $i <= 20; $i++) {
                $itemCol = "{$prefix}_{$i}_Item";
                if (! array_key_exists($itemCol, $rowArray)) {
                    break;
                }

                $label = (string) ($rowArray[$itemCol] ?? '');
                if (! filled($label)) {
                    continue;
                }

                $key = $this->matchConfigKeyByLabel($label, $sectionItems);
                if ($key === null) {
                    continue;
                }

                if ($this->isLegacyChecked($rowArray["{$prefix}_{$i}_SA"] ?? null)) {
                    $strength[] = $key;
                }
                if ($this->isLegacyChecked($rowArray["{$prefix}_{$i}_GA"] ?? null)) {
                    $growth[] = $key;
                }
            }
        }

        return [$strength, $growth];
    }

    /**
     * @return array<string, int>
     */
    private function legacyEvaluationPairs(object $row, string $prefix): array
    {
        $pairs = [];
        $rowArray = (array) $row;

        for ($i = 1; $i <= 20; $i++) {
            $itemCol = "{$prefix}_{$i}_Item";
            $valueCol = "{$prefix}_{$i}_Value";
            if (! array_key_exists($itemCol, $rowArray)) {
                break;
            }

            $label = (string) ($rowArray[$itemCol] ?? '');
            if (! filled($label)) {
                continue;
            }

            $pairs[$label] = (int) ($rowArray[$valueCol] ?? 0);
        }

        return $pairs;
    }

    /**
     * @param  array<string, string>  $configMap
     */
    private function matchConfigKeyByLabel(string $label, array $configMap): ?string
    {
        $normalizedLabel = trim($label);

        foreach ($configMap as $key => $configLabel) {
            if (trim((string) $configLabel) === $normalizedLabel) {
                return $key;
            }
        }

        return null;
    }

    private function isLegacyChecked(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === false) {
            return false;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return true;
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

    private function formatDateInput(mixed $value): string
    {
        if ($value === null || $value === '') {
            return now()->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return now()->format('Y-m-d');
        }
    }

    private function formatTimeInput(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '00:00';
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
