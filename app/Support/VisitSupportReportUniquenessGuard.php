<?php

namespace App\Support;

use App\Models\TeacherVisitSupportReport;
use Illuminate\Validation\ValidationException;

final class VisitSupportReportUniquenessGuard
{
    public static function assertNoCompletedDuplicate(
        int $teacherId,
        mixed $supportDate,
        ?int $ignoreReportId = null,
    ): void {
        $normalizedDate = ExcelSerialDate::toStorageString($supportDate);

        if ($normalizedDate === null) {
            return;
        }

        $query = TeacherVisitSupportReport::query()
            ->where('teacher_id', $teacherId)
            ->whereDate('support_date', $normalizedDate)
            ->where('status', '완료');

        if ($ignoreReportId !== null) {
            $query->whereKeyNot($ignoreReportId);
        }

        if (! $query->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'support_date' => '같은 교사의 같은 지원일에 완료 보고서가 이미 있습니다. 기존 보고서를 수정해 주세요.',
        ]);
    }
}
