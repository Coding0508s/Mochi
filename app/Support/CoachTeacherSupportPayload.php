<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Auth\Access\AuthorizationException;

final class CoachTeacherSupportPayload
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function applyTrustedContext(array $validated, Teacher $teacher): array
    {
        $validated['sk_code'] = self::trustedSkCode($teacher);
        $validated['institution_name'] = self::trustedInstitutionName($teacher);
        $validated['teacher_name'] = self::trustedTeacherName($teacher);

        return $validated;
    }

    public static function trustedSkCode(Teacher $teacher): string
    {
        $skCode = SkCodeNormalizer::normalize((string) ($teacher->SK_Code ?? ''));

        if ($skCode === null || $skCode === '') {
            throw new AuthorizationException('교사의 SK 코드가 유효하지 않아 저장할 수 없습니다.');
        }

        return $skCode;
    }

    public static function trustedInstitutionName(Teacher $teacher): string
    {
        $institutionName = trim((string) (InstitutionResolver::resolveForTeacher($teacher)?->resolvedAccountName() ?? ''));
        if ($institutionName !== '') {
            return $institutionName;
        }

        $schoolName = trim((string) ($teacher->School_Name ?? ''));
        if ($schoolName !== '') {
            return $schoolName;
        }

        throw new AuthorizationException('기관명을 확인할 수 없어 저장할 수 없습니다.');
    }

    public static function trustedTeacherName(Teacher $teacher): string
    {
        $teacherName = trim((string) ($teacher->Name ?? ''));
        if ($teacherName === '') {
            throw new AuthorizationException('교사명을 확인할 수 없어 저장할 수 없습니다.');
        }

        return $teacherName;
    }
}
