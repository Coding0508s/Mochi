<?php

namespace App\Support;

use App\Models\SupportRecord;

final class LegacyTeacherSupportReportLinker
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public static function findExistingSupportRecordId(string $action, array $validated): ?int
    {
        $supportType = TeacherSupportReportSupportRecordBuilder::supportTypeLabelForAction($action);
        if ($supportType === '') {
            return null;
        }

        $skCode = SkCodeNormalizer::normalize($validated['sk_code'] ?? '');
        $teacherName = (string) ($validated['teacher_name'] ?? '');
        $supportDate = (string) ($validated['support_date'] ?? '');

        if ($teacherName === '') {
            return null;
        }

        $query = SupportRecord::query()
            ->where('Target', $teacherName)
            ->where('Support_Type', $supportType);

        if (filled($skCode)) {
            $query->whereIn('SK_Code', SkCodeNormalizer::candidates($skCode));
        }

        if ($supportDate !== '') {
            $query->whereDate('Support_Date', $supportDate);
        }

        $coachName = (string) ($validated['coach_name'] ?? '');
        if ($coachName !== '') {
            $coachMatch = (clone $query)->where('TR_Name', $coachName)->orderByDesc('ID')->first();
            if ($coachMatch !== null) {
                return (int) $coachMatch->ID;
            }
        }

        $record = $query->orderByDesc('ID')->first();

        return $record !== null ? (int) $record->ID : null;
    }
}
