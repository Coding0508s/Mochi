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

    /** @var array<string, string> EMPNO → ENGLISHNAME(비어있지 않은 값) */
    private array $employeeEnglishByEmpno = [];

    /** @var array<string, string> EMPNO → KOREANAME(비어있지 않은 값) */
    private array $employeeKoreanByEmpno = [];

    /** @var array<string, string> lower(trim(EMAIL)) → ENGLISHNAME(첫 매칭 행) */
    private array $employeeEnglishByEmail = [];

    private bool $employeeTableExists = false;

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

        $this->employeeTableExists = true;

        // EMAIL 을 함께 읽어, 유저별 nameForCoReports() 재조회(N+1) 없이
        // EMPNO/EMAIL → 표시명을 메모리에서 해결할 수 있게 한다.
        $employees = Employee::query()->get(['EMPNO', 'KOREANAME', 'ENGLISHNAME', 'WORKDEPT', 'JOB', 'EMAIL']);

        foreach ($employees as $employee) {
            foreach ([$employee->KOREANAME, $employee->ENGLISHNAME] as $name) {
                $key = ManagerNameNormalizer::normalize(is_string($name) ? $name : null);
                if ($key !== '' && ! array_key_exists($key, $this->employeeByNormalizedName)) {
                    $this->employeeByNormalizedName[$key] = $employee;
                }
            }

            $empno = trim((string) ($employee->EMPNO ?? ''));
            if ($empno !== '') {
                $english = trim((string) ($employee->ENGLISHNAME ?? ''));
                if ($english !== '' && ! isset($this->employeeEnglishByEmpno[$empno])) {
                    $this->employeeEnglishByEmpno[$empno] = $english;
                }

                $korean = trim((string) ($employee->KOREANAME ?? ''));
                if ($korean !== '' && ! isset($this->employeeKoreanByEmpno[$empno])) {
                    $this->employeeKoreanByEmpno[$empno] = $korean;
                }
            }

            // value('ENGLISHNAME') 와 동일하게 같은 이메일의 "첫 행" 값을 보존한다(빈 값 포함).
            $email = mb_strtolower(trim((string) ($employee->EMAIL ?? '')));
            if ($email !== '' && ! array_key_exists($email, $this->employeeEnglishByEmail)) {
                $this->employeeEnglishByEmail[$email] = trim((string) ($employee->ENGLISHNAME ?? ''));
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

            $reportName = ManagerNameNormalizer::normalize($this->reportNameKey($user));
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

    /**
     * User::nameForCoReports() + preferredDisplayName() 와 동일한 표시명을,
     * 미리 적재한 employee 맵으로 추가 쿼리 없이 계산한다.
     *
     * 기존에는 유저마다 Employee 를 재조회(N+1)했다. 동작(반환 문자열)은 동일하게 보존한다.
     */
    private function reportNameKey(User $user): string
    {
        $empno = trim((string) ($user->employee_empno ?? ''));
        $email = mb_strtolower(trim((string) $user->email));

        if ($this->employeeTableExists) {
            if ($empno !== '' && ($byEmpNo = $this->employeeEnglishByEmpno[$empno] ?? '') !== '') {
                return $byEmpNo;
            }

            if ($email !== '' && trim($byEmail = ($this->employeeEnglishByEmail[$email] ?? '')) !== '') {
                return trim($byEmail);
            }
        }

        $fromUser = trim((string) $user->name);
        if ($fromUser !== '') {
            return $fromUser;
        }

        if ($empno !== '' && $this->employeeTableExists
            && ($korean = $this->employeeKoreanByEmpno[$empno] ?? '') !== '') {
            return $korean;
        }

        // preferredDisplayName() 포팅
        if ($email !== '' && $this->employeeTableExists
            && trim($english = ($this->employeeEnglishByEmail[$email] ?? '')) !== '') {
            return trim($english);
        }

        if ($fromUser !== '') {
            return $fromUser;
        }

        if ($email !== '') {
            return trim((string) $user->email);
        }

        return 'User';
    }
}
