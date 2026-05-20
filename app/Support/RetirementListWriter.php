<?php

namespace App\Support;

use App\Models\RetirementList;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class RetirementListWriter
{
    public function recordFromTeacher(Teacher $teacher, User $user): ?RetirementList
    {
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
            $cols['status'] => '퇴직',
            'FGC_LastModifier' => $userEmail,
            'FGC_LastModifyDate' => $now,
        ];

        $existing = RetirementList::query()
            ->where($teacherIdColumn, $teacher->ID)
            ->first();

        if ($existing) {
            $updateAttributes = $syncAttributes;

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
            $cols['recommend_yn'] => false,
            $cols['recommend_description'] => null,
            'FGC_Creator' => $userEmail,
            'FGC_CreateDate' => $now,
        ]));
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
