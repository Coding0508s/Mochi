<?php

namespace App\Support;

use App\Models\AccountAuditLog;
use App\Models\Employee;
use App\Models\JobTitlePermission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class JobTitlePermissionSynchronizer
{
    public const FLAG_COLUMNS = [
        'setup_view',
        'setup_manage',
        'can_manage_store_inventory',
        'is_gs_brochure_admin',
        'is_coach_team_lead',
        'can_view_all_institutions',
        'is_deputy_admin',
    ];

    public function syncUser(User $user, ?User $actor = null): bool
    {
        if ((bool) $user->is_admin) {
            return false;
        }

        $empNo = trim((string) $user->employee_empno);
        if ($empNo === '') {
            return false;
        }

        $jobCode = $this->resolveJobCode($empNo);
        $flags = $this->flagsForJobCode($jobCode);

        $before = $user->only(self::FLAG_COLUMNS);
        if ($this->flagsEqual($before, $flags)) {
            return false;
        }

        $user->forceFill($flags)->save();

        if (Schema::hasTable('account_audit_logs')) {
            AccountAuditLog::record($user, $actor, 'job_title_permission_synced', [
                'job_code' => $jobCode,
                'before' => $before,
                'after' => $flags,
            ]);
        }

        return true;
    }

    public function syncUsersForJobCode(string $jobCode, ?User $actor = null): int
    {
        $normalized = trim($jobCode);
        $empNos = Employee::query()
            ->whereRaw('TRIM(JOB) = ?', [$normalized])
            ->pluck('EMPNO')
            ->map(fn ($v): string => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($empNos === []) {
            return 0;
        }

        $users = User::query()
            ->whereIn('employee_empno', $empNos)
            ->where('is_admin', false)
            ->get();

        $count = 0;
        foreach ($users as $user) {
            if ($this->syncUser($user, $actor)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{synced: int, skipped_admin: int, skipped_no_employee: int}
     */
    public function syncAll(?User $actor = null): array
    {
        $stats = ['synced' => 0, 'skipped_admin' => 0, 'skipped_no_employee' => 0];

        $users = User::query()
            ->whereNotNull('employee_empno')
            ->where('employee_empno', '!=', '')
            ->get();

        foreach ($users as $user) {
            if ((bool) $user->is_admin) {
                $stats['skipped_admin']++;

                continue;
            }

            $empNo = trim((string) $user->employee_empno);
            if ($empNo === '' || ! Employee::query()->where('EMPNO', $empNo)->exists()) {
                $stats['skipped_no_employee']++;

                continue;
            }

            if ($this->syncUser($user, $actor)) {
                $stats['synced']++;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, bool>
     */
    public function flagsForJobCode(?string $jobCode): array
    {
        $normalized = trim((string) $jobCode);
        $row = $normalized === ''
            ? null
            : JobTitlePermission::query()->where('job_code', $normalized)->first();

        $flags = [];
        foreach (self::FLAG_COLUMNS as $column) {
            $flags[$column] = (bool) ($row?->{$column} ?? false);
        }

        if ($flags['setup_manage']) {
            $flags['setup_view'] = true;
        }

        return $flags;
    }

    private function resolveJobCode(string $empNo): ?string
    {
        $job = Employee::query()->where('EMPNO', $empNo)->value('JOB');

        if (! is_string($job)) {
            return null;
        }

        $trimmed = trim($job);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, bool>  $b
     */
    private function flagsEqual(array $a, array $b): bool
    {
        foreach (self::FLAG_COLUMNS as $column) {
            if ((bool) ($a[$column] ?? false) !== (bool) ($b[$column] ?? false)) {
                return false;
            }
        }

        return true;
    }
}
