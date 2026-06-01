<?php

namespace App\Support;

use App\Models\SupportRecord;

final class TeacherSupportReportSupportRecordBuilder
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function build(string $action, array $validated): array
    {
        $meetTime = self::resolveMeetTime($action, $validated);

        $attributes = [
            'Year' => (int) date('Y', strtotime((string) $validated['support_date'])),
            'SK_Code' => $validated['sk_code'],
            'Account_Name' => $validated['institution_name'],
            'TR_Name' => $validated['coach_name'],
            'Support_Date' => $validated['support_date'],
            'Meet_Time' => $meetTime,
            'Target' => $validated['teacher_name'],
            'Support_Type' => self::supportTypeLabel($action),
            'Issue' => self::resolveIssue($action, $validated),
        ];

        if ($action === 'demo_lesson') {
            $attributes['Others'] = filled($validated['other_notes'] ?? null)
                ? (string) $validated['other_notes']
                : null;
        }

        return SupportRecord::filterAttributesForTable($attributes);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private static function resolveMeetTime(string $action, array $validated): ?string
    {
        if ($action === 'demo_lesson') {
            return '00:00:00';
        }

        $interviewTime = trim((string) ($validated['interview_time'] ?? '00:00'));
        if ($interviewTime === '') {
            return '00:00:00';
        }

        return strlen($interviewTime) === 5 ? $interviewTime.':00' : $interviewTime;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private static function resolveIssue(string $action, array $validated): ?string
    {
        return match ($action) {
            'demo_lesson' => filled($validated['overall_comments'] ?? null)
                ? (string) $validated['overall_comments']
                : null,
            'open_class' => filled($validated['remarks'] ?? null)
                ? (string) $validated['remarks']
                : null,
            'pro_con', 'littleseed_con' => self::proConIssueSummary($validated),
            'unit21_plus', 'unit31_plus' => self::unitPlusIssueSummary($validated),
            default => filled($validated['other_notes'] ?? null)
                ? (string) $validated['other_notes']
                : null,
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private static function proConIssueSummary(array $validated): ?string
    {
        $summary = collect([
            $validated['teacher_issue'] ?? null,
            $validated['discussion_content'] ?? null,
            $validated['solution_plan'] ?? null,
        ])->filter()->implode("\n\n");

        return $summary !== '' ? $summary : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private static function unitPlusIssueSummary(array $validated): ?string
    {
        $summary = collect([
            $validated['verbal_comments'] ?? null,
            $validated['language_arts_comments'] ?? null,
            $validated['overall_comments'] ?? null,
        ])->filter()->implode("\n\n");

        return $summary !== '' ? $summary : null;
    }

    public static function supportTypeLabelForAction(string $action): string
    {
        return self::supportTypeLabel($action);
    }

    private static function supportTypeLabel(string $action): string
    {
        $configKey = match ($action) {
            'demo_lesson' => 'coach_teacher_demo_lesson',
            'lva_fr' => 'coach_teacher_lva_fr',
            'lva_fb' => 'coach_teacher_lva_fb',
            'ls_onsite_lva' => 'coach_teacher_ls_onsite_lva',
            'littleseed_con' => 'coach_teacher_littleseed_con',
            'onsite' => 'coach_teacher_onsite',
            'pro_con' => 'coach_teacher_pro_con',
            'open_class' => 'coach_teacher_open_class',
            'unit21_plus' => 'coach_teacher_unit21_plus',
            'unit31_plus' => 'coach_teacher_unit31_plus',
            default => null,
        };

        if ($configKey === null) {
            return '';
        }

        return (string) config("{$configKey}.support_type_label", '');
    }
}
