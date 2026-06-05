<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Carbon;

final class EmployeeEmpNoGenerator
{
    public static function next(?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'EMP-'.$date->format('Ymd').'-';

        $maxSequence = Employee::query()
            ->where('EMPNO', 'like', $prefix.'%')
            ->pluck('EMPNO')
            ->map(function (string $empNo) use ($prefix): ?int {
                if (! str_starts_with($empNo, $prefix)) {
                    return null;
                }

                $suffix = substr($empNo, strlen($prefix));

                return ctype_digit($suffix) ? (int) $suffix : null;
            })
            ->filter()
            ->max() ?? 0;

        return $prefix.str_pad((string) ($maxSequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
