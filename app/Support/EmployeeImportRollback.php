<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * 마지막 Employees 엑셀 import 적용분을 되돌리기 위한 스냅샷 저장소.
 */
final class EmployeeImportRollback
{
    public const CACHE_KEY = 'employee_import:last_rollback';

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function save(array $snapshot): void
    {
        if (! self::hasChanges($snapshot)) {
            return;
        }

        Cache::put(self::CACHE_KEY, $snapshot, now()->addDays(90));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        /** @var array<string, mixed>|null $snapshot */
        $snapshot = Cache::get(self::CACHE_KEY);

        return is_array($snapshot) ? $snapshot : null;
    }

    public static function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function hasPending(): bool
    {
        $snapshot = self::get();

        return $snapshot !== null && self::hasChanges($snapshot);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function hasChanges(array $snapshot): bool
    {
        return ($snapshot['inserted_empnos'] ?? []) !== []
            || ($snapshot['updated'] ?? []) !== []
            || ($snapshot['hidden'] ?? []) !== []
            || ($snapshot['departments_created'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function summaryLabel(array $snapshot): string
    {
        return sprintf(
            '신규 %d건 · 수정 %d건 · 숨김 %d건 · 신규 부서 %d건',
            count($snapshot['inserted_empnos'] ?? []),
            count($snapshot['updated'] ?? []),
            count($snapshot['hidden'] ?? []),
            count($snapshot['departments_created'] ?? []),
        );
    }
}
