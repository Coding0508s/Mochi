<?php

namespace App\Support;

final class LegacyTeacherSupportReportActionResolver
{
    public static function resolve(string $table, object $row): ?string
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
}
