<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmployeeExcelImporter
{
    /** @var array<string, string> */
    private array $departmentNameIndex = [];

    /** @var array<string, string> */
    private array $pendingDepartmentCodes = [];

    /** @var array<string, mixed>|null */
    private ?array $rollbackSnapshot = null;

    /**
     * @return array{
     *   inserted: int,
     *   updated: int,
     *   hidden: int,
     *   reactivated: int,
     *   departments_created: int,
     *   skipped: int,
     *   reset_emails_sent: int,
     *   reset_emails_failed: int,
     *   dry_run: bool,
     *   errors: array<int, string>
     * }
     */
    public function importFromFile(string $filePath, int $actorUserId, bool $dryRun = false): array
    {
        set_time_limit(120);

        $spreadsheet = IOFactory::load($filePath);
        $headerMissingResult = null;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if ($this->findHeaderRowIndex($rows) === null) {
                continue;
            }

            return $this->importRows($rows, $actorUserId, $dryRun);
        }

        return $headerMissingResult ?? [
            'inserted' => 0,
            'updated' => 0,
            'hidden' => 0,
            'reactivated' => 0,
            'departments_created' => 0,
            'skipped' => 0,
            'reset_emails_sent' => 0,
            'reset_emails_failed' => 0,
            'dry_run' => $dryRun,
            'errors' => ['헤더(성명, 부서, 모바일, Email)를 찾지 못했습니다.'],
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{
     *   inserted: int,
     *   updated: int,
     *   hidden: int,
     *   reactivated: int,
     *   departments_created: int,
     *   skipped: int,
     *   reset_emails_sent: int,
     *   reset_emails_failed: int,
     *   dry_run: bool,
     *   errors: array<int, string>
     * }
     */
    public function importRows(array $rows, int $actorUserId, bool $dryRun = false): array
    {
        $this->resetDepartmentCaches();

        $headerIndex = $this->findHeaderRowIndex($rows);
        if ($headerIndex === null) {
            return [
                'inserted' => 0,
                'updated' => 0,
                'hidden' => 0,
                'reactivated' => 0,
                'departments_created' => 0,
                'skipped' => 0,
                'reset_emails_sent' => 0,
                'reset_emails_failed' => 0,
                'dry_run' => $dryRun,
                'errors' => ['헤더(성명, 부서, 모바일, Email)를 찾지 못했습니다.'],
            ];
        }

        $headerMap = $this->buildHeaderMap($rows[$headerIndex] ?? []);
        $requiredHeaders = ['성명', '부서', '모바일', 'email'];
        foreach ($requiredHeaders as $requiredHeader) {
            if (! array_key_exists($requiredHeader, $headerMap)) {
                return [
                    'inserted' => 0,
                    'updated' => 0,
                    'hidden' => 0,
                    'reactivated' => 0,
                    'departments_created' => 0,
                    'skipped' => 0,
                    'reset_emails_sent' => 0,
                    'reset_emails_failed' => 0,
                    'dry_run' => $dryRun,
                    'errors' => ['헤더(성명, 부서, 모바일, Email)를 찾지 못했습니다.'],
                ];
            }
        }

        $this->loadDepartmentIndex();

        $this->rollbackSnapshot = $dryRun ? null : $this->beginRollback($actorUserId);

        $employeesByEmail = Employee::query()
            ->whereNotNull('EMAIL')
            ->where('EMAIL', '!=', '')
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->normalizeEmail((string) $employee->EMAIL));

        $result = [
            'inserted' => 0,
            'updated' => 0,
            'hidden' => 0,
            'reactivated' => 0,
            'departments_created' => 0,
            'skipped' => 0,
            'reset_emails_sent' => 0,
            'reset_emails_failed' => 0,
            'dry_run' => $dryRun,
            'errors' => [],
        ];

        /** @var array<string, true> $excelEmails */
        $excelEmails = [];
        /** @var array<string, true> $seenEmailsInFile */
        $seenEmailsInFile = [];

        for ($rowIndex = $headerIndex + 1; $rowIndex < count($rows); $rowIndex++) {
            $row = $rows[$rowIndex] ?? [];
            if ($this->isBlankRow($row)) {
                continue;
            }

            $displayRowNumber = $rowIndex + 1;
            $koreanName = trim((string) ($row[$headerMap['성명']] ?? ''));
            $departmentName = trim((string) ($row[$headerMap['부서']] ?? ''));
            $mobile = trim((string) ($row[$headerMap['모바일']] ?? ''));
            $emailRaw = trim((string) ($row[$headerMap['email']] ?? ''));

            if ($koreanName === '' && $emailRaw === '') {
                continue;
            }

            if ($emailRaw === '') {
                $result['errors'][] = "{$displayRowNumber}행: Email은 필수입니다.";
                $result['skipped']++;

                continue;
            }

            if (! filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
                $result['errors'][] = "{$displayRowNumber}행: Email 형식이 올바르지 않습니다.";
                $result['skipped']++;

                continue;
            }

            $normalizedEmail = $this->normalizeEmail($emailRaw);
            if (isset($seenEmailsInFile[$normalizedEmail])) {
                $result['errors'][] = "{$displayRowNumber}행: 파일 내 Email 중복({$emailRaw}).";
                $result['skipped']++;

                continue;
            }

            $seenEmailsInFile[$normalizedEmail] = true;
            $excelEmails[$normalizedEmail] = true;

            if ($koreanName === '') {
                $result['errors'][] = "{$displayRowNumber}행: 성명은 필수입니다.";
                $result['skipped']++;

                continue;
            }

            if ($departmentName === '') {
                $result['errors'][] = "{$displayRowNumber}행: 부서는 필수입니다.";
                $result['skipped']++;

                continue;
            }

            if (mb_strlen($departmentName) > 25) {
                $result['errors'][] = "{$displayRowNumber}행: 부서명은 25자 이하여야 합니다.";
                $result['skipped']++;

                continue;
            }

            $departmentResolution = $this->resolveDepartmentCode($departmentName, $dryRun);
            if ($departmentResolution['error'] !== null) {
                $result['errors'][] = "{$displayRowNumber}행: {$departmentResolution['error']}";
                $result['skipped']++;

                continue;
            }

            $workDept = $departmentResolution['dept_no'];
            if ($departmentResolution['created']) {
                $result['departments_created']++;
            }

            /** @var Employee|null $existing */
            $existing = $employeesByEmail->get($normalizedEmail);

            if ($existing === null) {
                if ($mobile === '') {
                    $result['errors'][] = "{$displayRowNumber}행: 신규 직원은 모바일이 필수입니다.";
                    $result['skipped']++;

                    continue;
                }

                $userConflict = $this->findConflictingUser($normalizedEmail, null);
                if ($userConflict !== null) {
                    $result['errors'][] = "{$displayRowNumber}행: {$userConflict}";
                    $result['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $result['inserted']++;

                    continue;
                }

                $empNo = EmployeeEmpNoGenerator::next();
                $defaultJob = (string) config('employee_import.default_job', 'Staff');

                Employee::query()->insert([
                    'EMPNO' => $empNo,
                    'KOREANAME' => $koreanName,
                    'ENGLISHNAME' => $koreanName,
                    'JOB' => $defaultJob,
                    'EMAIL' => $normalizedEmail,
                    'PHONENO' => $mobile,
                    'WORKDEPT' => $workDept,
                    'STATUS' => 1,
                    'HIREDATE' => EmployeeHireDate::defaultForStorage(),
                    'SEX' => EmployeeSex::normalizeForStorage(null),
                ]);

                /** @var Employee $employee */
                $employee = Employee::query()->where('EMPNO', $empNo)->firstOrFail();

                $employeesByEmail->put($normalizedEmail, $employee);

                $userPayload = [
                    'name' => $koreanName,
                    'email' => $normalizedEmail,
                    'employee_empno' => $empNo,
                    'password' => Str::random(48),
                    'is_admin' => false,
                    'is_active' => true,
                    'email_verified_at' => null,
                ];
                $inferredTeam = TeamMenuContext::inferUserTeamForRegistration($workDept, $defaultJob);
                if ($inferredTeam !== null) {
                    $userPayload['team'] = $inferredTeam;
                }

                User::query()->create($userPayload);

                $this->trackInsertedEmployee($empNo);

                $result['inserted']++;

                continue;
            }

            $wasInactive = (int) ($existing->STATUS ?? 0) !== 1;

            if ($dryRun) {
                if ($wasInactive) {
                    $result['reactivated']++;
                }
                $result['updated']++;

                continue;
            }

            $this->trackUpdatedEmployee($existing);

            $existing->KOREANAME = $koreanName;
            $existing->WORKDEPT = $workDept;
            if ($mobile !== '') {
                $existing->PHONENO = $mobile;
            }
            $existing->EMAIL = $normalizedEmail;
            $existing->STATUS = 1;
            $existing->save();

            if ($wasInactive) {
                $result['reactivated']++;
            }

            $this->syncLinkedUserForUpdate($existing, $koreanName, $normalizedEmail, $workDept);
            $result['updated']++;
        }

        $hideResult = $this->hideEmployeesNotInExcel($excelEmails, $actorUserId, $dryRun);
        $result['hidden'] = $hideResult['hidden'];
        $result['skipped'] += $hideResult['skipped'];
        $result['errors'] = array_merge($result['errors'], $hideResult['errors']);

        if ($this->rollbackSnapshot !== null && EmployeeImportRollback::hasChanges($this->rollbackSnapshot)) {
            $result['rollback'] = $this->rollbackSnapshot;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *   deleted_employees: int,
     *   deleted_users: int,
     *   restored_updates: int,
     *   restored_hidden: int,
     *   deleted_departments: int,
     *   errors: array<int, string>
     * }
     */
    public function rollback(array $snapshot): array
    {
        $result = [
            'deleted_employees' => 0,
            'deleted_users' => 0,
            'restored_updates' => 0,
            'restored_hidden' => 0,
            'deleted_departments' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($snapshot, &$result): void {
            foreach ($snapshot['inserted_empnos'] ?? [] as $empNo) {
                $empNo = trim((string) $empNo);
                if ($empNo === '') {
                    continue;
                }

                $deletedUsers = User::query()->where('employee_empno', $empNo)->delete();
                $result['deleted_users'] += $deletedUsers;

                if (Employee::query()->where('EMPNO', $empNo)->delete() > 0) {
                    $result['deleted_employees']++;
                }
            }

            foreach ($snapshot['updated'] ?? [] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $empNo = trim((string) ($entry['empno'] ?? ''));
                $employeeData = $entry['employee'] ?? null;
                if ($empNo === '' || ! is_array($employeeData)) {
                    continue;
                }

                $employee = Employee::query()->where('EMPNO', $empNo)->first();
                if ($employee === null) {
                    $result['errors'][] = "수정 복원 건너뜀({$empNo}): 직원을 찾을 수 없습니다.";

                    continue;
                }

                $employee->forceFill([
                    'KOREANAME' => $employeeData['KOREANAME'] ?? $employee->KOREANAME,
                    'WORKDEPT' => $employeeData['WORKDEPT'] ?? $employee->WORKDEPT,
                    'PHONENO' => $employeeData['PHONENO'] ?? $employee->PHONENO,
                    'EMAIL' => $employeeData['EMAIL'] ?? $employee->EMAIL,
                    'STATUS' => $employeeData['STATUS'] ?? $employee->STATUS,
                ])->save();

                $userData = $entry['user'] ?? null;
                if (is_array($userData) && isset($userData['id'])) {
                    $user = User::query()->find($userData['id']);
                    if ($user !== null) {
                        $user->forceFill([
                            'name' => $userData['name'] ?? $user->name,
                            'email' => $userData['email'] ?? $user->email,
                            'employee_empno' => $userData['employee_empno'] ?? $user->employee_empno,
                            'is_active' => $userData['is_active'] ?? $user->is_active,
                            'team' => $userData['team'] ?? $user->team,
                        ])->save();
                    }
                }

                $result['restored_updates']++;
            }

            foreach ($snapshot['hidden'] ?? [] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $empNo = trim((string) ($entry['empno'] ?? ''));
                if ($empNo === '') {
                    continue;
                }

                $employee = Employee::query()->where('EMPNO', $empNo)->first();
                if ($employee === null) {
                    $result['errors'][] = "숨김 복원 건너뜀({$empNo}): 직원을 찾을 수 없습니다.";

                    continue;
                }

                $employee->forceFill([
                    'STATUS' => $entry['status'] ?? 1,
                ])->save();

                $userData = $entry['user'] ?? null;
                if (is_array($userData) && isset($userData['id'])) {
                    $user = User::query()->find($userData['id']);
                    if ($user !== null && array_key_exists('is_active', $userData)) {
                        $user->forceFill(['is_active' => (bool) $userData['is_active']])->save();
                    }
                }

                $result['restored_hidden']++;
            }

            foreach ($snapshot['departments_created'] ?? [] as $deptNo) {
                $deptNo = trim((string) $deptNo);
                if ($deptNo === '' || str_starts_with($deptNo, 'DRY-')) {
                    continue;
                }

                $stillUsed = Employee::query()->where('WORKDEPT', $deptNo)->exists();
                if ($stillUsed) {
                    $result['errors'][] = "부서 삭제 건너뜀({$deptNo}): 다른 직원이 사용 중입니다.";

                    continue;
                }

                if (Department::query()->where('DEPTNO', $deptNo)->delete() > 0) {
                    $result['deleted_departments']++;
                }
            }
        });

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function beginRollback(int $actorUserId): array
    {
        return [
            'applied_at' => now()->toIso8601String(),
            'actor_user_id' => $actorUserId,
            'inserted_empnos' => [],
            'updated' => [],
            'hidden' => [],
            'departments_created' => [],
        ];
    }

    private function trackInsertedEmployee(string $empNo): void
    {
        if ($this->rollbackSnapshot === null) {
            return;
        }

        $this->rollbackSnapshot['inserted_empnos'][] = $empNo;
    }

    private function trackUpdatedEmployee(Employee $employee): void
    {
        if ($this->rollbackSnapshot === null) {
            return;
        }

        $empNo = trim((string) $employee->EMPNO);

        foreach ($this->rollbackSnapshot['updated'] as $entry) {
            if (is_array($entry) && ($entry['empno'] ?? null) === $empNo) {
                return;
            }
        }

        $this->rollbackSnapshot['updated'][] = [
            'empno' => $empNo,
            'employee' => [
                'KOREANAME' => $employee->KOREANAME,
                'WORKDEPT' => $employee->WORKDEPT,
                'PHONENO' => $employee->PHONENO,
                'EMAIL' => $employee->EMAIL,
                'STATUS' => $employee->STATUS,
            ],
            'user' => $this->userRollbackSnapshot($this->linkedUserForEmployee($employee)),
        ];
    }

    private function trackHiddenEmployee(Employee $employee): void
    {
        if ($this->rollbackSnapshot === null) {
            return;
        }

        $linkedUser = $this->linkedUserForEmployee($employee);
        $userSnapshot = null;
        if ($linkedUser !== null) {
            $userSnapshot = [
                'id' => $linkedUser->id,
                'is_active' => $linkedUser->is_active,
            ];
        }

        $this->rollbackSnapshot['hidden'][] = [
            'empno' => trim((string) $employee->EMPNO),
            'status' => (int) ($employee->STATUS ?? 1),
            'user' => $userSnapshot,
        ];
    }

    private function trackCreatedDepartment(string $deptNo): void
    {
        if ($this->rollbackSnapshot === null || $deptNo === '' || str_starts_with($deptNo, 'DRY-')) {
            return;
        }

        if (! in_array($deptNo, $this->rollbackSnapshot['departments_created'], true)) {
            $this->rollbackSnapshot['departments_created'][] = $deptNo;
        }
    }

    private function linkedUserForEmployee(Employee $employee): ?User
    {
        $empNo = trim((string) $employee->EMPNO);
        $linkedUser = User::query()
            ->where('employee_empno', $empNo)
            ->first();

        if ($linkedUser !== null) {
            return $linkedUser;
        }

        $normalizedEmail = $this->normalizeEmail((string) $employee->EMAIL);
        if ($normalizedEmail === '') {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$normalizedEmail])
            ->first();
    }

    /**
     * @return array{id: int, name: string, email: string, employee_empno: ?string, is_active: bool, team: ?string}|null
     */
    private function userRollbackSnapshot(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'employee_empno' => $user->employee_empno,
            'is_active' => (bool) $user->is_active,
            'team' => $user->team,
        ];
    }

    private function resetDepartmentCaches(): void
    {
        $this->departmentNameIndex = [];
        $this->pendingDepartmentCodes = [];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function findHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $headerMap = $this->buildHeaderMap($row);
            if (array_key_exists('성명', $headerMap) && array_key_exists('email', $headerMap)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $cell) {
            $label = trim((string) $cell);
            if ($label === '') {
                continue;
            }

            if ($label === '성명') {
                $map['성명'] = $index;
            } elseif ($label === '부서') {
                $map['부서'] = $index;
            } elseif ($label === '모바일') {
                $map['모바일'] = $index;
            } elseif (strcasecmp($label, 'Email') === 0) {
                $map['email'] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function loadDepartmentIndex(): void
    {
        $this->departmentNameIndex = [];

        foreach (Department::query()->get() as $department) {
            $name = trim((string) $department->DEPTNAME);
            if ($name === '') {
                continue;
            }

            $this->departmentNameIndex[mb_strtolower($name)] = (string) $department->DEPTNO;
        }

        if (Department::query()->where('DEPTNO', DepartmentDisplay::COACH_DEPT_NO)->exists()) {
            foreach (['training', 'training team', 'coach'] as $alias) {
                $this->departmentNameIndex[$alias] = DepartmentDisplay::COACH_DEPT_NO;
            }
        }
    }

    /**
     * @return array{dept_no: string, created: bool, error: ?string}
     */
    private function resolveDepartmentCode(string $departmentName, bool $dryRun): array
    {
        $lookupKey = $this->departmentLookupKey($departmentName);

        if ($lookupKey === '') {
            return ['dept_no' => '', 'created' => false, 'error' => '부서명이 비어 있습니다.'];
        }

        if (isset($this->departmentNameIndex[$lookupKey])) {
            return [
                'dept_no' => $this->departmentNameIndex[$lookupKey],
                'created' => false,
                'error' => null,
            ];
        }

        if (isset($this->pendingDepartmentCodes[$lookupKey])) {
            return [
                'dept_no' => $this->pendingDepartmentCodes[$lookupKey],
                'created' => false,
                'error' => null,
            ];
        }

        if ($dryRun) {
            $placeholder = 'DRY-'.strtoupper(substr(md5($lookupKey), 0, 4));
            $this->pendingDepartmentCodes[$lookupKey] = $placeholder;
            $this->departmentNameIndex[$lookupKey] = $placeholder;

            return [
                'dept_no' => $placeholder,
                'created' => true,
                'error' => null,
            ];
        }

        $deptNo = DepartmentCodeGenerator::next();

        Department::query()->create([
            'DEPTNO' => $deptNo,
            'DEPTNAME' => trim($departmentName),
            'MGRNO' => '',
            'ADMRDEPT' => '',
            'LOCATION' => '',
        ]);

        $this->trackCreatedDepartment($deptNo);

        $this->pendingDepartmentCodes[$lookupKey] = $deptNo;
        $this->departmentNameIndex[$lookupKey] = $deptNo;

        return [
            'dept_no' => $deptNo,
            'created' => true,
            'error' => null,
        ];
    }

    private function departmentLookupKey(string $departmentName): string
    {
        $trimmed = trim($departmentName);
        $lower = mb_strtolower($trimmed);

        if (in_array($lower, ['training', 'training team', 'coach'], true)) {
            return $lower;
        }

        return $lower;
    }

    private function findConflictingUser(string $normalizedEmail, ?string $empNo): ?string
    {
        $conflict = User::query()
            ->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$normalizedEmail])
            ->first();

        if ($conflict === null) {
            return null;
        }

        $linkedEmpNo = trim((string) ($conflict->employee_empno ?? ''));
        if ($empNo !== null && ($linkedEmpNo === '' || $linkedEmpNo === $empNo)) {
            return null;
        }

        return '이미 다른 로그인 계정에서 사용 중인 Email입니다.';
    }

    private function syncLinkedUserForUpdate(
        Employee $employee,
        string $koreanName,
        string $normalizedEmail,
        string $workDept,
    ): void {
        $empNo = trim((string) $employee->EMPNO);
        $linkedUser = User::query()
            ->where('employee_empno', $empNo)
            ->first();

        if ($linkedUser === null) {
            $linkedUser = User::query()
                ->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$normalizedEmail])
                ->first();
        }

        if ($linkedUser === null) {
            return;
        }

        $conflictMessage = $this->findConflictingUser($normalizedEmail, $empNo);
        if ($conflictMessage !== null) {
            return;
        }

        $payload = [
            'name' => $koreanName,
            'email' => $normalizedEmail,
            'employee_empno' => $empNo,
            'is_active' => true,
        ];

        $job = trim((string) ($employee->JOB ?? ''));
        $inferredTeam = TeamMenuContext::inferUserTeamForRegistration($workDept, $job);
        if ($inferredTeam !== null && ! $linkedUser->is_admin && ! $linkedUser->is_deputy_admin) {
            $payload['team'] = $inferredTeam;
        }

        $linkedUser->forceFill($payload)->save();
    }

    /**
     * @param  array<string, true>  $excelEmails
     * @return array{hidden: int, skipped: int, errors: array<int, string>}
     */
    private function hideEmployeesNotInExcel(array $excelEmails, int $actorUserId, bool $dryRun): array
    {
        $result = [
            'hidden' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $candidates = Employee::query()
            ->where('STATUS', 1)
            ->whereNotNull('EMAIL')
            ->where('EMAIL', '!=', '')
            ->get();

        foreach ($candidates as $employee) {
            $normalizedEmail = $this->normalizeEmail((string) $employee->EMAIL);
            if ($normalizedEmail === '' || isset($excelEmails[$normalizedEmail])) {
                continue;
            }

            $skipReason = $this->hideSkipReason($employee, $actorUserId);
            if ($skipReason !== null) {
                $result['skipped']++;
                $result['errors'][] = "숨김 건너뜀({$employee->EMPNO}): {$skipReason}";

                continue;
            }

            if ($dryRun) {
                $result['hidden']++;

                continue;
            }

            $this->trackHiddenEmployee($employee);

            $employee->STATUS = 0;
            $employee->save();

            $empNo = trim((string) $employee->EMPNO);
            $linkedUser = User::query()
                ->where('employee_empno', $empNo)
                ->first();

            if ($linkedUser !== null) {
                $linkedUser->forceFill(['is_active' => false])->save();
            }

            $result['hidden']++;
        }

        return $result;
    }

    private function hideSkipReason(Employee $employee, int $actorUserId): ?string
    {
        $empNo = trim((string) $employee->EMPNO);
        $linkedUser = User::query()
            ->where('employee_empno', $empNo)
            ->first();

        if ($linkedUser === null) {
            $normalizedEmail = $this->normalizeEmail((string) $employee->EMAIL);
            if ($normalizedEmail !== '') {
                $linkedUser = User::query()
                    ->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$normalizedEmail])
                    ->first();
            }
        }

        if ($linkedUser !== null && (int) $linkedUser->getAuthIdentifier() === $actorUserId) {
            return '현재 로그인한 관리자 계정은 숨길 수 없습니다.';
        }

        if ($linkedUser !== null && $linkedUser->is_active && $linkedUser->is_admin) {
            $otherActiveAdmins = User::query()
                ->where('is_active', true)
                ->where('is_admin', true)
                ->whereKeyNot($linkedUser->id)
                ->count();

            if ($otherActiveAdmins === 0) {
                return '마지막 활성 관리자 계정은 숨길 수 없습니다.';
            }
        }

        return null;
    }
}
