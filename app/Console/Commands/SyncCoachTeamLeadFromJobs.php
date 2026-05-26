<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use App\Support\CoachTeamLeadEligibility;
use Illuminate\Console\Command;

class SyncCoachTeamLeadFromJobs extends Command
{
    protected $signature = 'users:sync-coach-team-lead-from-jobs {--revoke-ineligible : 조건 미충족 사용자의 is_coach_team_lead 를 false 로 내립니다}';

    protected $description = 'employee JOB/WORKDEPT/STATUS 기준으로 users.is_coach_team_lead 를 동기화합니다.';

    public function handle(): int
    {
        $users = User::query()
            ->whereNotNull('employee_empno')
            ->where('employee_empno', '!=', '')
            ->get(['id', 'employee_empno', 'is_coach_team_lead']);

        if ($users->isEmpty()) {
            $this->info('동기화 대상 사용자가 없습니다.');

            return self::SUCCESS;
        }

        $employees = Employee::query()
            ->whereIn('EMPNO', $users->pluck('employee_empno')->filter()->unique()->values())
            ->get(['EMPNO', 'JOB', 'WORKDEPT', 'STATUS'])
            ->keyBy('EMPNO');

        $promoted = 0;
        $revoked = 0;
        $skippedNoEmployee = 0;

        $shouldRevoke = (bool) $this->option('revoke-ineligible');

        foreach ($users as $user) {
            $empNo = trim((string) $user->employee_empno);
            $employee = $employees->get($empNo);

            if (! $employee) {
                $skippedNoEmployee++;

                continue;
            }

            $eligible = CoachTeamLeadEligibility::recommendsTeamKpi(
                (string) ($employee->JOB ?? ''),
                (string) ($employee->WORKDEPT ?? ''),
                isset($employee->STATUS) ? (int) $employee->STATUS : null,
            );

            $current = (bool) $user->is_coach_team_lead;
            if ($eligible && ! $current) {
                $user->forceFill(['is_coach_team_lead' => true])->save();
                $promoted++;

                continue;
            }

            if ($shouldRevoke && ! $eligible && $current) {
                $user->forceFill(['is_coach_team_lead' => false])->save();
                $revoked++;
            }
        }

        $this->table(
            ['항목', '건수'],
            [
                ['권한 부여', $promoted],
                ['권한 해제(--revoke-ineligible)', $revoked],
                ['직원 정보 없음으로 건너뜀', $skippedNoEmployee],
            ],
        );

        return self::SUCCESS;
    }
}
