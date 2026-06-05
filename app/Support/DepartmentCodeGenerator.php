<?php

namespace App\Support;

use App\Models\Department;

final class DepartmentCodeGenerator
{
    public static function next(): string
    {
        $maxNumber = Department::query()
            ->where('DEPTNO', 'like', 'A%')
            ->pluck('DEPTNO')
            ->map(function (string $deptNo): ?int {
                if (! preg_match('/^A(\d+)$/', $deptNo, $matches)) {
                    return null;
                }

                return (int) $matches[1];
            })
            ->filter()
            ->max() ?? 0;

        return 'A'.str_pad((string) ($maxNumber + 1), 2, '0', STR_PAD_LEFT);
    }
}
