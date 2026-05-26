<?php

namespace App\Support;

use App\Models\RetirementList;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class RetirementListWriter
{
    public function recordFromTeacher(
        Teacher $teacher,
        User $user,
        ?TeacherRetirementRecommendation $recommendation = null,
    ): ?RetirementList {
        if (! Schema::hasTable('S_RetirementList')) {
            return null;
        }

        $teacher->loadMissing(['institution.accountInfo']);

        $cols = config('coach_retired_teachers.columns');
        $teacherIdColumn = $cols['teacher_id'];
        $now = now();
        $userEmail = (string) ($user->email ?? '');

        $syncAttributes = [
            $cols['name'] => $teacher->Name,
            $cols['sk_code'] => $teacher->SK_Code,
            $cols['account_name'] => $this->accountNameFor($teacher),
            $cols['description'] => $teacher->Description,
            $cols['status'] => config('coach_retired_teachers.statuses.retired', '퇴직'),
            'FGC_LastModifier' => $userEmail,
            'FGC_LastModifyDate' => $now,
        ];

        $existing = RetirementList::query()
            ->where($teacherIdColumn, $teacher->ID)
            ->first();

        $recommendAttributes = $this->recommendAttributes($cols, $recommendation);

        if ($existing) {
            $updateAttributes = array_merge($syncAttributes, $recommendAttributes);

            if (blank($existing->TR_Name)) {
                $updateAttributes[$cols['tr_name']] = (string) ($teacher->institution?->accountInfo?->TR ?? '');
            }

            $existing->update($updateAttributes);

            return $existing->refresh();
        }

        return RetirementList::query()->create(array_merge($syncAttributes, [
            $teacherIdColumn => $teacher->ID,
            $cols['tr_name'] => (string) ($teacher->institution?->accountInfo?->TR ?? ''),
            $cols['retirement_date'] => $now,
            'FGC_Creator' => $userEmail,
            'FGC_CreateDate' => $now,
        ], $recommendAttributes ?: [
            $cols['recommend_yn'] => false,
            $cols['recommend_description'] => null,
        ]));
    }

    public function deleteForTeacher(int $teacherId): int
    {
        if (! Schema::hasTable('S_RetirementList')) {
            return 0;
        }

        $teacherIdColumn = config('coach_retired_teachers.columns.teacher_id');

        return RetirementList::query()
            ->where($teacherIdColumn, $teacherId)
            ->delete();
    }

    public function markReinstatedFromTeacher(Teacher $teacher, User $user): ?RetirementList
    {
        if (! Schema::hasTable('S_RetirementList')) {
            return null;
        }

        $cols = config('coach_retired_teachers.columns');
        $teacherIdColumn = $cols['teacher_id'];
        $now = now();
        $userEmail = (string) ($user->email ?? '');

        $existing = RetirementList::query()
            ->where($teacherIdColumn, $teacher->ID)
            ->first();

        if (! $existing) {
            return null;
        }

        $existing->update([
            $cols['status'] => config('coach_retired_teachers.statuses.reinstated', '복직'),
            'FGC_LastModifier' => $userEmail,
            'FGC_LastModifyDate' => $now,
        ]);

        return $existing->refresh();
    }

    /**
     * @param  array<string, string>  $cols
     * @return array<string, bool|string|null>
     */
    private function recommendAttributes(array $cols, ?TeacherRetirementRecommendation $recommendation): array
    {
        if ($recommendation === null) {
            return [];
        }

        return [
            $cols['recommend_yn'] => $recommendation->recommendYn,
            $cols['recommend_description'] => $recommendation->recommendDescription,
        ];
    }

    private function accountNameFor(Teacher $teacher): string
    {
        $fromInstitution = trim($teacher->institution?->resolvedAccountName() ?? '');
        if ($fromInstitution !== '') {
            return $fromInstitution;
        }

        return trim((string) ($teacher->School_Name ?? ''));
    }
}
