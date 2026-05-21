<?php

namespace App\Support;

/**
 * department.DEPTNAME 표시용 (레거시 Training → Coach).
 */
final class DepartmentDisplay
{
    public const COACH_DEPT_NO = 'A05';

    public const LEGACY_TRAINING_NAME = 'Training';

    public const COACH_NAME = 'Coach';

    public static function name(?string $deptNo, ?string $deptName): string
    {
        $code = trim((string) $deptNo);
        $label = trim((string) $deptName);

        if ($code === self::COACH_DEPT_NO && ($label === '' || strcasecmp($label, self::LEGACY_TRAINING_NAME) === 0)) {
            return self::COACH_NAME;
        }

        if ($label !== '') {
            return $label;
        }

        return $code;
    }
}
