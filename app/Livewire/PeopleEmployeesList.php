<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class PeopleEmployeesList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $searchType = 'name'; // name | email | department

    public string $filterStatus = '';   // '', '1', '0', ...

    public string $filterDept = '';

    public string $sortField = 'KOREANAME';

    public string $sortDirection = 'asc';

    public bool $showEditModal = false;

    public string $editingEmpNo = '';

    public string $editKoreanName = '';

    public string $editEnglishName = '';

    public string $editJob = '';

    public string $editEmail = '';

    public string $editPhone = '';

    public string $editStatus = '';

    public string $editWorkDept = '';

    public bool $showCreateTeamModal = false;

    public string $newDeptName = '';

    public bool $showDeleteTeamModal = false;

    public string $deleteDeptNo = '';

    public bool $showCreateEmployeeModal = false;

    public string $createEmpNo = '';

    public string $createKoreanName = '';

    public string $createEnglishName = '';

    public string $createJob = '';

    public string $createEmail = '';

    public string $createPhone = '';

    public string $createStatus = '1';

    public string $createWorkDept = '';

    public ?string $createHireDate = null;

    public bool $createIsGsBrochureAdmin = false;

    public bool $hasLinkedLoginAccount = false;

    public ?int $linkedUserId = null;

    public bool $editUserIsActive = true;

    public bool $editUserIsAdmin = false;

    public bool $editGsBrochureAdmin = false;

    protected array $queryString = [
        'filterDept' => ['as' => 'team', 'except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSearchType(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDept(): void
    {
        $this->resetPage();
    }

    public function updatedEditStatus($value): void
    {
        $this->editUserIsActive = $this->shouldActivateUserFromEmployeeStatus(
            $value === null ? null : (string) $value
        );
    }

    public function sort(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function openEditModal(string $empNo): void
    {
        Gate::authorize('editEmployeeProfile');

        $employee = Employee::query()->where('EMPNO', $empNo)->first();
        if (! $employee) {
            return;
        }

        $this->editingEmpNo = (string) $employee->EMPNO;
        $this->editKoreanName = (string) ($employee->KOREANAME ?? '');
        $this->editEnglishName = (string) ($employee->ENGLISHNAME ?? '');
        $this->editJob = (string) ($employee->JOB ?? '');
        $this->editEmail = (string) ($employee->EMAIL ?? '');
        $this->editPhone = (string) ($employee->PHONENO ?? '');
        $this->editStatus = $employee->STATUS === null ? '' : (string) $employee->STATUS;
        $this->editWorkDept = (string) ($employee->WORKDEPT ?? '');

        $linkedUser = $this->resolveLinkedUser($employee);

        $this->hasLinkedLoginAccount = $linkedUser !== null;
        $this->linkedUserId = $linkedUser?->id;
        $this->editUserIsActive = $this->shouldActivateUserFromEmployeeStatus($this->editStatus);
        $this->editUserIsAdmin = (bool) ($linkedUser?->is_admin ?? false);
        $this->editGsBrochureAdmin = (bool) ($linkedUser?->is_gs_brochure_admin ?? false);

        $this->resetErrorBag();
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;

        $this->editingEmpNo = '';
        $this->editKoreanName = '';
        $this->editEnglishName = '';
        $this->editJob = '';
        $this->editEmail = '';
        $this->editPhone = '';
        $this->editStatus = '';
        $this->editWorkDept = '';
        $this->hasLinkedLoginAccount = false;
        $this->linkedUserId = null;
        $this->editUserIsActive = true;
        $this->editUserIsAdmin = false;
        $this->editGsBrochureAdmin = false;

        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function saveEmployee(): void
    {
        Gate::authorize('editEmployeeProfile');

        $deptCodes = $this->getDeptOptions()
            ->pluck('WORKDEPT')
            ->map(fn ($deptCode) => (string) $deptCode)
            ->all();
        $jobOptions = $this->getJobOptions()
            ->map(fn ($job) => (string) $job)
            ->all();

        $jobRules = ['required', 'string', 'max:100'];
        if ($jobOptions !== []) {
            $jobRules[] = Rule::in($jobOptions);
        }

        $validated = $this->validate([
            'editKoreanName' => ['required', 'string', 'max:20'],
            'editEnglishName' => ['required', 'string', 'max:50'],
            'editJob' => $jobRules,
            'editEmail' => ['required', 'email', 'max:100'],
            'editPhone' => ['required', 'string', 'max:20'],
            'editStatus' => ['nullable', 'in:0,1'],
            'editWorkDept' => ['required', 'string', Rule::in($deptCodes)],
            'editUserIsAdmin' => ['boolean'],
            'editGsBrochureAdmin' => ['boolean'],
        ], [
            'editKoreanName.required' => '이름(한글)은 필수입니다.',
            'editEnglishName.required' => '영어 이름은 필수입니다.',
            'editJob.required' => '직책은 필수입니다.',
            'editEmail.required' => '이메일은 필수입니다.',
            'editEmail.email' => '이메일 형식이 올바르지 않습니다.',
            'editPhone.required' => '연락처는 필수입니다.',
            'editWorkDept.required' => '부서는 필수입니다.',
            'editWorkDept.in' => '선택 가능한 부서를 선택해 주세요.',
            'editStatus.in' => '상태 값이 올바르지 않습니다.',
            'editJob.in' => '직책은 목록에서 선택해 주세요.',
        ]);

        $this->editUserIsActive = $this->shouldActivateUserFromEmployeeStatus($validated['editStatus'] ?? null);

        $canManageUserAccounts = Gate::allows('manageUserAccounts')
            && (bool) config('features.people_modal_account_edit_enabled', true);
        $newlyCreatedUserEmail = null;
        $resetLinkSentForNewUser = true;

        try {
            DB::transaction(function () use ($validated, $canManageUserAccounts, &$newlyCreatedUserEmail): void {
                $employee = Employee::query()
                    ->where('EMPNO', $this->editingEmpNo)
                    ->lockForUpdate()
                    ->first();

                if (! $employee) {
                    throw ValidationException::withMessages([
                        'editKoreanName' => ['수정 대상 직원을 찾을 수 없습니다.'],
                    ]);
                }

                $previousWorkDept = (string) ($employee->WORKDEPT ?? '');
                if ($previousWorkDept !== $validated['editWorkDept']) {
                    Gate::authorize('manageEmployeeDepartment');
                }

                $employee->KOREANAME = trim($validated['editKoreanName']);
                $employee->ENGLISHNAME = trim($validated['editEnglishName']);
                $employee->JOB = trim($validated['editJob']);
                $employee->EMAIL = trim($validated['editEmail']);
                $employee->PHONENO = trim($validated['editPhone']);
                $employee->WORKDEPT = $validated['editWorkDept'];
                $employee->STATUS = $validated['editStatus'] === '' ? null : (int) $validated['editStatus'];
                $employee->save();

                if (! $canManageUserAccounts) {
                    return;
                }

                $currentEmployeeEmpNo = trim((string) ($employee->EMPNO ?? ''));
                $normalizedEmail = mb_strtolower(trim((string) $validated['editEmail']));
                if ($normalizedEmail === '') {
                    throw ValidationException::withMessages([
                        'editEmail' => ['직원 계정 생성을 위해 이메일은 필수입니다.'],
                    ]);
                }

                $linkedUser = null;
                if ($this->linkedUserId !== null) {
                    $linkedUser = User::query()
                        ->whereKey($this->linkedUserId)
                        ->lockForUpdate()
                        ->first();
                }

                if (! $linkedUser) {
                    $linkedByEmail = User::query()
                        ->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$normalizedEmail])
                        ->lockForUpdate()
                        ->first();

                    if ($linkedByEmail) {
                        $linkedByEmailEmpNo = trim((string) ($linkedByEmail->employee_empno ?? ''));
                        if ($linkedByEmailEmpNo !== '' && $linkedByEmailEmpNo !== $currentEmployeeEmpNo) {
                            throw ValidationException::withMessages([
                                'editEmail' => ['이미 다른 직원 계정에서 사용 중인 이메일입니다.'],
                            ]);
                        }

                        $linkedUser = $linkedByEmail;
                    } else {
                        $linkedUser = User::query()->create([
                            'name' => trim((string) $validated['editKoreanName']),
                            'email' => $normalizedEmail,
                            'employee_empno' => $currentEmployeeEmpNo,
                            'password' => Str::random(48),
                            'is_admin' => (bool) $this->editUserIsAdmin,
                            'is_gs_brochure_admin' => (bool) $this->editGsBrochureAdmin,
                            'is_active' => (bool) $this->editUserIsActive,
                            'email_verified_at' => null,
                        ]);
                        $newlyCreatedUserEmail = $normalizedEmail;
                    }
                }

                $emailConflictExists = User::query()
                    ->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$normalizedEmail])
                    ->whereKeyNot($linkedUser->id)
                    ->exists();
                if ($emailConflictExists) {
                    throw ValidationException::withMessages([
                        'editEmail' => ['이미 다른 로그인 계정에서 사용 중인 이메일입니다.'],
                    ]);
                }

                $currentUser = auth()->user();
                if ($currentUser !== null
                    && (int) $currentUser->getAuthIdentifier() === (int) $linkedUser->id
                    && ! $this->editUserIsActive
                ) {
                    throw ValidationException::withMessages([
                        'editStatus' => ['현재 로그인한 계정은 비활성화할 수 없습니다.'],
                    ]);
                }

                $isCurrentlyActiveAdmin = (bool) $linkedUser->is_active && (bool) $linkedUser->is_admin;
                $willRemainActiveAdmin = $this->editUserIsActive && $this->editUserIsAdmin;
                if ($isCurrentlyActiveAdmin && ! $willRemainActiveAdmin) {
                    $otherActiveAdminCount = User::query()
                        ->where('is_active', true)
                        ->where('is_admin', true)
                        ->whereKeyNot($linkedUser->id)
                        ->count();

                    if ($otherActiveAdminCount === 0) {
                        throw ValidationException::withMessages([
                            'editUserIsAdmin' => ['마지막 활성 관리자 계정은 관리자 권한을 해제하거나 비활성화할 수 없습니다.'],
                        ]);
                    }
                }

                $linkedUser->forceFill([
                    'name' => trim((string) $validated['editKoreanName']),
                    'email' => $normalizedEmail,
                    'employee_empno' => $currentEmployeeEmpNo,
                    'is_active' => $this->editUserIsActive,
                    'is_admin' => $this->editUserIsAdmin,
                    'is_gs_brochure_admin' => $this->editGsBrochureAdmin,
                ])->save();
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        if (is_string($newlyCreatedUserEmail) && $newlyCreatedUserEmail !== '') {
            $resetLinkSentForNewUser = $this->sendResetLinkSafely($newlyCreatedUserEmail);
        }

        $this->closeEditModal();
        if ($resetLinkSentForNewUser) {
            session()->flash('success', '직원 정보가 저장되었습니다.');
        } else {
            session()->flash('success', '직원 정보와 로그인 계정이 저장되었습니다.');
            session()->flash('error', '메일 서버 인증 문제로 비밀번호 설정 메일 발송에 실패했습니다. 메일 설정을 확인해 주세요.');
        }
    }

    public function openCreateTeamModal(): void
    {
        Gate::authorize('editEmployeeProfile');

        $this->newDeptName = '';
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showCreateTeamModal = true;
    }

    public function openCreateEmployeeModal(): void
    {
        Gate::authorize('manageEmployeeDepartment');

        $this->createEmpNo = '';
        $this->createKoreanName = '';
        $this->createEnglishName = '';
        $this->createJob = '';
        $this->createEmail = '';
        $this->createPhone = '';
        $this->createStatus = '1';
        $this->createWorkDept = '';
        $this->createHireDate = null;
        $this->createIsGsBrochureAdmin = false;
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showCreateEmployeeModal = true;
    }

    public function closeCreateEmployeeModal(): void
    {
        $this->showCreateEmployeeModal = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function createEmployee(): void
    {
        Gate::authorize('manageEmployeeDepartment');

        $deptCodes = $this->getDeptOptions()
            ->pluck('WORKDEPT')
            ->map(fn ($deptCode) => (string) $deptCode)
            ->all();

        $jobOptions = $this->getJobOptions()
            ->map(fn ($job) => (string) $job)
            ->all();

        $jobRules = ['required', 'string', 'max:100'];
        if ($jobOptions !== []) {
            $jobRules[] = Rule::in($jobOptions);
        }

        $emailRules = ['required', 'email', 'max:100', Rule::unique('users', 'email')];

        $validated = $this->validate([
            'createEmpNo' => ['required', 'string', 'max:20', Rule::unique('employee', 'EMPNO')],
            'createKoreanName' => ['required', 'string', 'max:20'],
            'createEnglishName' => ['required', 'string', 'max:50'],
            'createJob' => $jobRules,
            'createEmail' => $emailRules,
            'createPhone' => ['required', 'string', 'max:20'],
            'createStatus' => ['nullable', 'in:0,1'],
            'createWorkDept' => ['required', 'string', Rule::in($deptCodes)],
            'createHireDate' => ['nullable', 'date'],
            'createIsGsBrochureAdmin' => ['boolean'],
        ], [
            'createEmpNo.required' => '사번은 필수입니다.',
            'createEmpNo.unique' => '이미 등록된 사번입니다.',
            'createKoreanName.required' => '이름(한글)은 필수입니다.',
            'createEnglishName.required' => '영어 이름은 필수입니다.',
            'createJob.required' => '직책은 필수입니다.',
            'createEmail.required' => '이메일은 필수입니다.',
            'createEmail.email' => '이메일 형식이 올바르지 않습니다.',
            'createEmail.unique' => '이미 로그인 계정이 있는 이메일입니다. 다른 이메일을 쓰거나 계정 발급을 해제하세요.',
            'createPhone.required' => '연락처는 필수입니다.',
            'createWorkDept.required' => '부서는 필수입니다.',
            'createWorkDept.in' => '선택 가능한 부서를 선택해 주세요.',
            'createStatus.in' => '상태 값이 올바르지 않습니다.',
            'createJob.in' => '직책은 목록에서 선택해 주세요.',
        ]);

        $email = strtolower(trim($validated['createEmail']));

        DB::transaction(function () use ($validated, $email): void {
            Employee::query()->create([
                'EMPNO' => trim($validated['createEmpNo']),
                'KOREANAME' => trim($validated['createKoreanName']),
                'ENGLISHNAME' => trim($validated['createEnglishName']),
                'JOB' => trim($validated['createJob']),
                'EMAIL' => $email,
                'PHONENO' => trim($validated['createPhone']),
                'WORKDEPT' => $validated['createWorkDept'],
                'STATUS' => $validated['createStatus'] === '' || $validated['createStatus'] === null ? null : (int) $validated['createStatus'],
                'HIREDATE' => $validated['createHireDate'] ?? null,
            ]);

            User::query()->create([
                'name' => trim($validated['createKoreanName']),
                'email' => $email,
                'employee_empno' => trim($validated['createEmpNo']),
                'password' => Str::random(48),
                'is_admin' => false,
                'is_gs_brochure_admin' => (bool) ($validated['createIsGsBrochureAdmin'] ?? false),
                'is_active' => true,
                'email_verified_at' => null,
            ]);
        });

        $resetLinkSent = $this->sendResetLinkSafely($email);

        $this->closeCreateEmployeeModal();
        $this->resetPage();
        if ($resetLinkSent) {
            session()->flash('success', '신규 직원이 등록되었고, 로그인 비밀번호 설정 안내 메일을 발송했습니다.');
        } else {
            session()->flash('success', '신규 직원과 로그인 계정이 등록되었습니다.');
            session()->flash('error', '메일 서버 인증 문제로 비밀번호 설정 메일 발송에 실패했습니다. 메일 설정을 확인해 주세요.');
        }
    }

    public function closeCreateTeamModal(): void
    {
        $this->showCreateTeamModal = false;
        $this->newDeptName = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function createTeam(): void
    {
        Gate::authorize('editEmployeeProfile');

        $validated = $this->validate([
            'newDeptName' => ['required', 'string', 'max:25'],
        ], [
            'newDeptName.required' => '팀명은 필수입니다.',
            'newDeptName.max' => '팀명은 25자 이하로 입력해 주세요.',
        ]);

        $newDeptNo = $this->nextDeptNo();

        Department::query()->create([
            'DEPTNO' => $newDeptNo,
            'DEPTNAME' => trim($validated['newDeptName']),
            'MGRNO' => '',
            'ADMRDEPT' => '',
            'LOCATION' => '',
        ]);

        $this->closeCreateTeamModal();
        session()->flash('success', "새 팀({$newDeptNo})이 생성되었습니다.");
    }

    public function openDeleteTeamModal(): void
    {
        Gate::authorize('editEmployeeProfile');

        $this->deleteDeptNo = '';
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showDeleteTeamModal = true;
    }

    public function closeDeleteTeamModal(): void
    {
        $this->showDeleteTeamModal = false;
        $this->deleteDeptNo = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function deleteTeam(): void
    {
        Gate::authorize('editEmployeeProfile');

        $validated = $this->validate([
            'deleteDeptNo' => ['required', 'string', Rule::exists('department', 'DEPTNO')],
        ], [
            'deleteDeptNo.required' => '삭제할 팀을 선택해 주세요.',
            'deleteDeptNo.exists' => '선택한 팀이 존재하지 않습니다.',
        ]);

        $deptNo = (string) $validated['deleteDeptNo'];

        $employeeCount = Employee::query()
            ->where('WORKDEPT', $deptNo)
            ->count();

        if ($employeeCount > 0) {
            $this->addError('deleteDeptNo', "해당 팀에 소속된 직원 {$employeeCount}명이 있어 삭제할 수 없습니다.");

            return;
        }

        $deleted = Department::query()
            ->where('DEPTNO', $deptNo)
            ->delete();

        if (! $deleted) {
            $this->addError('deleteDeptNo', '삭제 대상 팀을 찾을 수 없습니다.');

            return;
        }

        if ($this->filterDept === $deptNo) {
            $this->filterDept = '';
        }

        $this->closeDeleteTeamModal();
        session()->flash('success', '팀이 삭제되었습니다.');
    }

    public function render()
    {
        $allowedSortFields = ['EMPNO', 'KOREANAME', 'ENGLISHNAME', 'JOB', 'WORKDEPT', 'EMAIL', 'HIREDATE', 'STATUS'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'KOREANAME';

        $query = Employee::query()
            ->select('employee.*')
            ->leftJoin('department as d', 'employee.WORKDEPT', '=', 'd.DEPTNO')
            ->addSelect(DB::raw('d.DEPTNAME as DEPARTMENT_NAME'))
            ->when($this->filterStatus !== '', function ($q) {
                $q->where('employee.STATUS', (int) $this->filterStatus);
            })
            ->when($this->filterDept !== '', function ($q) {
                $q->where('employee.WORKDEPT', $this->filterDept);
            })
            ->when(trim($this->search) !== '', function ($q) {
                $keyword = preg_replace('/\s+/u', '', trim($this->search)) ?? '';
                if ($keyword === '') {
                    return;
                }

                if ($this->searchType === 'email') {
                    $q->whereRaw("REPLACE(employee.EMAIL, ' ', '') like ?", ["%{$keyword}%"]);
                } elseif ($this->searchType === 'department') {
                    $q->where(function ($sub) use ($keyword) {
                        $sub->whereRaw("REPLACE(employee.WORKDEPT, ' ', '') like ?", ["%{$keyword}%"])
                            ->orWhereRaw("REPLACE(COALESCE(d.DEPTNAME, ''), ' ', '') like ?", ["%{$keyword}%"]);
                    });
                } else {
                    $q->where(function ($sub) use ($keyword) {
                        $sub->whereRaw("REPLACE(employee.KOREANAME, ' ', '') like ?", ["%{$keyword}%"])
                            ->orWhereRaw("REPLACE(employee.ENGLISHNAME, ' ', '') like ?", ["%{$keyword}%"]);
                    });
                }
            });

        $employees = $query
            ->orderBy("employee.{$sortField}", $this->sortDirection)
            ->paginate(20);

        $allCount = Employee::query()->count();
        $activeCount = Employee::query()->where('STATUS', 1)->count();
        $inactiveCount = Employee::query()->where('STATUS', 0)->count();

        $deptOptions = $this->getDeptOptions();

        $statusOptions = Employee::query()
            ->whereNotNull('STATUS')
            ->distinct()
            ->orderBy('STATUS')
            ->pluck('STATUS');
        $jobOptions = $this->getJobOptions();

        return view('livewire.people-employees-list', [
            'employees' => $employees,
            'allCount' => $allCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'deptOptions' => $deptOptions,
            'statusOptions' => $statusOptions,
            'jobOptions' => $jobOptions,
            'currentTeamLabel' => $this->resolveCurrentTeamLabel($deptOptions),
            'canManageEmployees' => Gate::allows('editEmployeeProfile'),
            'canManageEmployeeDepartment' => Gate::allows('manageEmployeeDepartment'),
            'canManageUserAccounts' => Gate::allows('manageUserAccounts'),
            'isPeopleModalAccountEditEnabled' => (bool) config('features.people_modal_account_edit_enabled', true),
        ]);
    }

    private function resolveCurrentTeamLabel($deptOptions): string
    {
        if ($this->filterDept === '') {
            return '전체';
        }

        $matched = $deptOptions->firstWhere('WORKDEPT', $this->filterDept);
        if (! $matched) {
            return $this->filterDept;
        }

        return (string) ($matched->dept_name ?: $matched->WORKDEPT);
    }

    private function getDeptOptions()
    {
        return Department::query()
            ->selectRaw('DEPTNO as WORKDEPT')
            ->selectRaw('DEPTNAME as dept_name')
            ->orderBy('DEPTNO')
            ->get();
    }

    private function getJobOptions()
    {
        return Employee::query()
            ->whereNotNull('JOB')
            ->where('JOB', '!=', '')
            ->select('JOB')
            ->distinct()
            ->orderBy('JOB')
            ->pluck('JOB');
    }

    private function resolveLinkedUser(Employee $employee): ?User
    {
        $employeeEmpNo = trim((string) ($employee->EMPNO ?? ''));
        $useAccountLink = (bool) config('features.people_use_account_link', true);

        if ($useAccountLink && $employeeEmpNo !== '') {
            $linkedByEmpNo = User::query()
                ->where('employee_empno', $employeeEmpNo)
                ->first(['id', 'is_active', 'is_admin', 'is_gs_brochure_admin']);

            if ($linkedByEmpNo) {
                return $linkedByEmpNo;
            }
        }

        $allowEmailFallback = (bool) config('features.people_account_email_fallback_enabled', false);
        if (! $allowEmailFallback) {
            return null;
        }

        $normalizedEmail = mb_strtolower(trim((string) ($employee->EMAIL ?? '')));
        if ($normalizedEmail === '') {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$normalizedEmail])
            ->first(['id', 'is_active', 'is_admin', 'is_gs_brochure_admin']);
    }

    private function shouldActivateUserFromEmployeeStatus(?string $employeeStatus): bool
    {
        return (string) $employeeStatus !== '0';
    }

    private function sendResetLinkSafely(string $email): bool
    {
        try {
            $status = Password::sendResetLink(['email' => $email]);

            return $status === Password::RESET_LINK_SENT;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function nextDeptNo(): string
    {
        $maxNumber = (int) (Department::query()
            ->whereRaw("DEPTNO REGEXP '^A[0-9]{2,}$'")
            ->selectRaw('MAX(CAST(SUBSTRING(DEPTNO, 2) AS UNSIGNED)) as max_number')
            ->value('max_number') ?? 0);

        $nextNumber = $maxNumber + 1;

        return 'A'.str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
    }
}
