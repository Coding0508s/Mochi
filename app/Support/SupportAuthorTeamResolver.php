<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * 지원 보고서 작성자(TR_Name / coach_name) → CO / Coach / CS 팀 분류.
 *
 * 우선순위: employee(WORKDEPT) → users.team / 연결 employee → JOB → unknown
 */
final class SupportAuthorTeamResolver
{
    public const TEAM_CO = 'co';

    public const TEAM_COACH = 'coach';

    public const TEAM_CS = 'cs';

    public const TEAM_UNKNOWN = 'unknown';

    /** @var array<string, Employee|null> */
    private array $employeeByNormalizedName = [];

    /** @var array<string, User|null> */
    private array $userByNormalizedName = [];

    private bool $indexesLoaded = false;

    public function resolve(string $authorName): string
    {
        $this->ensureIndexesLoaded();

        $normalized = ManagerNameNormalizer::normalize($authorName);
        if ($normalized === '') {
            return self::TEAM_UNKNOWN;
        }

        $employee = $this->employeeByNormalizedName[$normalized] ?? null;
        if ($employee instanceof Employee) {
            $fromWorkDept = TeamMenuContext::inferUserTeamFromWorkDept((string) ($employee->WORKDEPT ?? ''));
            if ($fromWorkDept !== null) {
                return $this->mapContextTeamCode($fromWorkDept);
            }

            $fromJob = TeamMenuContext::inferUserTeamFromJob((string) ($employee->JOB ?? ''));
            if ($fromJob !== null) {
                return $this->mapContextTeamCode($fromJob);
            }
        }

        $user = $this->userByNormalizedName[$normalized] ?? null;
        if ($user instanceof User) {
            $teamCode = TeamMenuContext::resolveTeamCode($user);
            if ($teamCode !== '') {
                return $this->mapContextTeamCode($teamCode);
            }
        }

        return self::TEAM_UNKNOWN;
    }

    private function mapContextTeamCode(string $teamCode): string
    {
        return match (mb_strtoupper(trim($teamCode))) {
            'CO' => self::TEAM_CO,
            'CS' => self::TEAM_CS,
            'COACH', 'TR', 'TRAINING' => self::TEAM_COACH,
            default => self::TEAM_UNKNOWN,
        };
    }

    private function ensureIndexesLoaded(): void
    {
        if ($this->indexesLoaded) {
            return;
        }

        $this->loadEmployeeIndex();
        $this->loadUserIndex();
        $this->indexesLoaded = true;
    }

    private function loadEmployeeIndex(): void
    {
        if (! Schema::hasTable('employee')) {
            return;
        }

        $employees = Employee::query()->get(['EMPNO', 'KOREANAME', 'ENGLISHNAME', 'WORKDEPT', 'JOB']);

        foreach ($employees as $employee) {
            foreach ([$employee->KOREANAME, $employee->ENGLISHNAME] as $name) {
                $key = ManagerNameNormalizer::normalize(is_string($name) ? $name : null);
                if ($key !== '' && ! array_key_exists($key, $this->employeeByNormalizedName)) {
                    $this->employeeByNormalizedName[$key] = $employee;
                }
            }
        }
    }

    private function loadUserIndex(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $users = User::query()
            ->when(
                Schema::hasTable('employee'),
                fn ($query) => $query->with('employee'),
            )
            ->get(['id', 'name', 'email', 'employee_empno', 'team']);

        foreach ($users as $user) {
            $keys = [ManagerNameNormalizer::normalize($user->name)];

            $reportName = ManagerNameNormalizer::normalize($user->nameForCoReports());
            if ($reportName !== '') {
                $keys[] = $reportName;
            }

            if ($user->relationLoaded('employee') && $user->employee instanceof Employee) {
                foreach ([$user->employee->KOREANAME, $user->employee->ENGLISHNAME] as $name) {
                    $keys[] = ManagerNameNormalizer::normalize(is_string($name) ? $name : null);
                }
            }

            foreach (array_unique(array_filter($keys)) as $key) {
                if (! array_key_exists($key, $this->userByNormalizedName)) {
                    $this->userByNormalizedName[$key] = $user;
                }
            }
        }
    }
}
