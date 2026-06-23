<?php

namespace App\Livewire;

use App\Actions\SetTemporaryUserPassword;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\DepartmentCodeGenerator;
use App\Support\DepartmentDisplay;
use App\Support\EmployeeExcelImporter;
use App\Support\EmployeeImportRollback;
use App\Support\EmployeeSex;
use App\Support\TeamMenuContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class PeopleEmployeesList extends Component
{
    use WithFileUploads;
    use WithPagination;

    public const IMPORT_RESET_CONFIRMATION_PHRASE = '엑셀 초기화';

    public string $search = '';

    public string $searchType = 'name'; // name | email | department

    public string $filterStatus = '1';   // '1' 재직(기본), '0' 비활성, '' 전체

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

    public bool $showSendResetModal = false;

    public string $resetTargetEmpNo = '';

    public string $resetTargetName = '';

    public string $resetTargetEmail = '';

    /** 'send_only' (계정 있음 → 메일만) | 'create_and_send' (계정 없음 → 생성 후 메일) */
    public string $resetTargetMode = 'send_only';

    public bool $showTempPasswordConfirmModal = false;

    public bool $showTempPasswordResultModal = false;

    public string $tempPasswordTargetEmpNo = '';

    public string $tempPasswordTargetName = '';

    public string $tempPasswordTargetEmail = '';

    public string $issuedTempPassword = '';

    public bool $tempPasswordPrivilegedConfirm = false;

    public bool $tempPasswordTargetIsPrivileged = false;

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

    public string $createSex = '';

    /** @var TemporaryUploadedFile|null */
    public $importFile = null;

    /** @var array<string, mixed>|null */
    public ?array $importPreview = null;

    public ?string $importNotice = null;

    /** @var array<int, string> */
    public array $importErrors = [];

    public bool $showImportResetModal = false;

    public string $importResetConfirmationText = '';

    public bool $hasLinkedLoginAccount = false;

    public ?int $linkedUserId = null;

    public bool $editUserIsActive = true;

    public bool $editIsAdmin = false;

    public bool $editIsDeputyAdmin = false;

    public bool $editSetupView = false;

    public bool $editSetupManage = false;

    public bool $editIsGsBrochureAdmin = false;

    public bool $editCanManageStoreInventory = false;

    public bool $editCanViewAllInstitutions = false;

    private ?bool $supportsSetupPermissionColumns = null;

    private ?bool $supportsCanViewAllInstitutionsColumn = null;

    protected array $queryString = [
        'filterDept' => ['as' => 'team', 'except' => ''],
        'filterStatus' => ['as' => 'status', 'except' => '1'],
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

    public function updatedEditIsAdmin(bool $value): void
    {
        if ($value) {
            $this->editIsDeputyAdmin = false;
            $this->editSetupView = true;
            $this->editSetupManage = true;
        }
    }

    public function updatedEditIsDeputyAdmin(bool $value): void
    {
        if ($value) {
            $this->editIsAdmin = false;
            $this->editSetupView = true;
        }
    }

    public function updatedEditSetupManage(bool $value): void
    {
        if ($value) {
            $this->editSetupView = true;
        }
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
        $this->editIsAdmin = (bool) ($linkedUser?->is_admin);
        $this->editIsDeputyAdmin = (bool) ($linkedUser?->is_deputy_admin);
        $this->editSetupView = (bool) ($linkedUser?->setup_view);
        $this->editSetupManage = (bool) ($linkedUser?->setup_manage);
        $this->editIsGsBrochureAdmin = (bool) ($linkedUser?->is_gs_brochure_admin);
        $this->editCanManageStoreInventory = (bool) ($linkedUser?->can_manage_store_inventory);
        $this->editCanViewAllInstitutions = $this->supportsCanViewAllInstitutionsColumn()
            ? (bool) ($linkedUser?->can_view_all_institutions)
            : false;

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
        $this->editIsAdmin = false;
        $this->editIsDeputyAdmin = false;
        $this->editSetupView = false;
        $this->editSetupManage = false;
        $this->editIsGsBrochureAdmin = false;
        $this->editCanManageStoreInventory = false;
        $this->editCanViewAllInstitutions = false;

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
                $departmentChanged = $previousWorkDept !== $validated['editWorkDept'];
                if ($departmentChanged) {
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

                if ($departmentChanged) {
                    $this->forgetInstitutionManagerOptionCaches();
                }

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
                        $newUserPayload = [
                            'name' => trim((string) $validated['editKoreanName']),
                            'email' => $normalizedEmail,
                            'employee_empno' => $currentEmployeeEmpNo,
                            'password' => Str::random(48),
                            'is_admin' => false,
                            'is_gs_brochure_admin' => false,
                            'can_manage_store_inventory' => false,
                            'is_coach_team_lead' => false,
                            'is_deputy_admin' => false,
                            'can_view_all_institutions' => false,
                            'is_active' => (bool) $this->editUserIsActive,
                            'email_verified_at' => null,
                        ];
                        if ($this->supportsSetupPermissionColumns()) {
                            $newUserPayload['setup_view'] = false;
                            $newUserPayload['setup_manage'] = false;
                        }
                        $syncedTeam = TeamMenuContext::inferUserTeamForRegistration(
                            $validated['editWorkDept'],
                            trim($validated['editJob'])
                        );
                        if ($syncedTeam !== null) {
                            $newUserPayload['team'] = $syncedTeam;
                        }
                        $linkedUser = User::query()->create($newUserPayload);
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

                $isCurrentlyAdmin = (bool) $linkedUser->is_admin;
                $isDeactivating = ! $this->editUserIsActive && (bool) $linkedUser->is_active;
                if ($isCurrentlyAdmin && $isDeactivating) {
                    $otherActiveAdminCount = User::query()
                        ->where('is_active', true)
                        ->where('is_admin', true)
                        ->whereKeyNot($linkedUser->id)
                        ->count();

                    if ($otherActiveAdminCount === 0) {
                        throw ValidationException::withMessages([
                            'editStatus' => ['마지막 활성 관리자 계정은 비활성화할 수 없습니다.'],
                        ]);
                    }
                }

                $linkedUserPayload = [
                    'name' => trim((string) $validated['editKoreanName']),
                    'email' => $normalizedEmail,
                    'employee_empno' => $currentEmployeeEmpNo,
                    'is_active' => $this->editUserIsActive,
                ];

                $nextIsAdmin = (bool) $this->editIsAdmin;
                $nextIsDeputyAdmin = $nextIsAdmin ? false : (bool) $this->editIsDeputyAdmin;
                $nextSetupManage = $nextIsAdmin ? true : (bool) $this->editSetupManage;
                $nextSetupView = $nextIsAdmin ? true : ((bool) $this->editSetupView || $nextSetupManage || $nextIsDeputyAdmin);

                $linkedUserPayload['is_admin'] = $nextIsAdmin;
                $linkedUserPayload['is_deputy_admin'] = $nextIsDeputyAdmin;
                $linkedUserPayload['is_gs_brochure_admin'] = (bool) $this->editIsGsBrochureAdmin;
                $linkedUserPayload['can_manage_store_inventory'] = (bool) $this->editCanManageStoreInventory;
                if ($this->supportsCanViewAllInstitutionsColumn()) {
                    $linkedUserPayload['can_view_all_institutions'] = (bool) $this->editCanViewAllInstitutions;
                }
                if ($this->supportsSetupPermissionColumns()) {
                    $linkedUserPayload['setup_view'] = $nextSetupView;
                    $linkedUserPayload['setup_manage'] = $nextSetupManage;
                }

                $syncedTeam = TeamMenuContext::inferUserTeamForRegistration(
                    $validated['editWorkDept'],
                    trim($validated['editJob'])
                );
                $linkedUserPayload['team'] = $syncedTeam ?? '';
                $linkedUser->forceFill($linkedUserPayload)->save();
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        if (is_string($newlyCreatedUserEmail) && $newlyCreatedUserEmail !== '') {
            $status = $this->sendResetLink($newlyCreatedUserEmail);
            $resetLinkSentForNewUser = $status === Password::RESET_LINK_SENT;
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
        $this->createSex = '';
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
            'createSex' => ['nullable', 'string', Rule::in(EmployeeSex::allowedValues())],
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
            'createSex.in' => '성별 값이 올바르지 않습니다.',
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
                'SEX' => EmployeeSex::normalizeForStorage($validated['createSex'] ?? null),
            ]);

            $userPayload = [
                'name' => trim($validated['createKoreanName']),
                'email' => $email,
                'employee_empno' => trim($validated['createEmpNo']),
                'password' => Str::random(48),
                'is_admin' => false,
                'is_gs_brochure_admin' => false,
                'can_manage_store_inventory' => false,
                'is_coach_team_lead' => false,
                'is_deputy_admin' => false,
                'can_view_all_institutions' => false,
                'is_active' => true,
                'email_verified_at' => null,
            ];
            $inferredTeam = TeamMenuContext::inferUserTeamForRegistration(
                $validated['createWorkDept'],
                trim($validated['createJob'])
            );
            if ($inferredTeam !== null) {
                $userPayload['team'] = $inferredTeam;
            }
            User::query()->create($userPayload);
        });

        $resetLinkSent = $this->sendResetLink($email) === Password::RESET_LINK_SENT;

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

        $newDeptNo = DepartmentCodeGenerator::next();

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
        Gate::authorize('deleteTeamStructure');

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

    /**
     * 비밀번호 재설정 메일 발송 확인 모달을 엽니다.
     *
     * 직원 상태(계정 유무·활성 여부·재직 여부)에 따라 4가지 동작 분기:
     *  - 계정 있음 + 활성              → send_only 모드로 모달 표시
     *  - 계정 있음 + 비활성            → 거부 (flash error)
     *  - 계정 없음 + 직원 재직(STATUS=1) → create_and_send 모드로 모달 표시
     *  - 계정 없음 + 직원 퇴사/비활성  → 거부 (flash error)
     */
    public function openSendResetModal(string $empNo): void
    {
        Gate::authorize('manageUserAccounts');

        $employee = Employee::query()->where('EMPNO', $empNo)->first();
        if (! $employee) {
            session()->flash('error', '대상 직원을 찾을 수 없습니다.');

            return;
        }

        $linkedUser = $this->resolveLinkedUser($employee);
        $employeeIsActive = (int) ($employee->STATUS ?? -1) === 1;

        if ($linkedUser !== null) {
            if (! (bool) $linkedUser->is_active) {
                session()->flash('error', '비활성 계정에는 비밀번호 재설정 메일을 보낼 수 없습니다. 먼저 계정을 활성화해 주세요.');

                return;
            }

            $this->resetTargetMode = 'send_only';
            $this->resetTargetEmail = (string) $linkedUser->email;
        } else {
            if (! $employeeIsActive) {
                session()->flash('error', '재직 중이 아닌 직원에게는 로그인 계정을 만들 수 없습니다.');

                return;
            }

            $employeeEmail = trim((string) ($employee->EMAIL ?? ''));
            if ($employeeEmail === '') {
                session()->flash('error', '직원 이메일이 비어 있어 계정을 만들 수 없습니다. 먼저 직원 정보를 수정해 주세요.');

                return;
            }

            $this->resetTargetMode = 'create_and_send';
            $this->resetTargetEmail = $employeeEmail;
        }

        $this->resetTargetEmpNo = (string) $employee->EMPNO;
        $this->resetTargetName = (string) ($employee->KOREANAME ?? '');
        $this->showSendResetModal = true;
    }

    public function closeSendResetModal(): void
    {
        $this->showSendResetModal = false;
        $this->resetTargetEmpNo = '';
        $this->resetTargetName = '';
        $this->resetTargetEmail = '';
        $this->resetTargetMode = 'send_only';
    }

    /**
     * 확인 모달에서 [발송] 버튼을 누른 시점의 실제 처리.
     *
     * - send_only: 기존 계정에 메일만 발송
     * - create_and_send: 최소 권한·활성 상태로 계정을 생성한 뒤 메일 발송
     *
     * 양쪽 모두 메일 발송 상태(success/throttled/invalid_user/exception)에 따라
     * 사용자에게 보이는 flash 메시지를 분기하고, 모든 시도를 Log::info로 남깁니다.
     */
    public function sendPasswordResetLink(): void
    {
        Gate::authorize('manageUserAccounts');

        $employee = Employee::query()->where('EMPNO', $this->resetTargetEmpNo)->first();
        if (! $employee) {
            session()->flash('error', '대상 직원을 찾을 수 없습니다.');
            $this->closeSendResetModal();

            return;
        }

        if ($this->resetTargetMode === 'create_and_send') {
            try {
                $this->createMinimalLoginAccountFor($employee);
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first() ?: '계정을 만들 수 없습니다.';
                session()->flash('error', (string) $message);
                $this->closeSendResetModal();

                return;
            }
        }

        $email = mb_strtolower(trim((string) $this->resetTargetEmail));
        $status = $this->sendResetLink($email);

        Log::info('[admin] password reset link send attempted', [
            'admin_id' => auth()->id(),
            'target_empno' => $this->resetTargetEmpNo,
            'target_email' => $email,
            'mode' => $this->resetTargetMode,
            'status' => $status,
        ]);

        $this->closeSendResetModal();
        $this->flashSendStatus($status);
    }

    /**
     * 직원 정보 수정 모달의 "비밀번호 재설정 메일 보내기" 버튼에서 호출.
     * 모달이 열려 있는 컨텍스트(editingEmpNo)를 사용해 확인 모달을 띄웁니다.
     */
    public function openSendResetModalFromEdit(): void
    {
        if ($this->editingEmpNo === '') {
            return;
        }

        $this->openSendResetModal($this->editingEmpNo);
    }

    public function openTempPasswordModal(string $empNo): void
    {
        Gate::authorize('manageUserAccounts');

        $employee = Employee::query()->where('EMPNO', $empNo)->first();
        if (! $employee) {
            session()->flash('error', '대상 직원을 찾을 수 없습니다.');

            return;
        }

        $linkedUser = $this->resolveLinkedUser($employee);
        if ($linkedUser === null) {
            session()->flash('error', '연결된 로그인 계정이 없습니다.');

            return;
        }

        if (! (bool) $linkedUser->is_active) {
            session()->flash('error', '비활성 계정에는 임시 비밀번호를 발급할 수 없습니다. 먼저 계정을 활성화해 주세요.');

            return;
        }

        if ($linkedUser->id === auth()->id()) {
            session()->flash('error', '본인 계정에는 임시 비밀번호를 발급할 수 없습니다.');

            return;
        }

        $this->tempPasswordTargetEmpNo = (string) $employee->EMPNO;
        $this->tempPasswordTargetName = (string) ($employee->KOREANAME ?? '');
        $this->tempPasswordTargetEmail = (string) $linkedUser->email;
        $this->tempPasswordTargetIsPrivileged = app(SetTemporaryUserPassword::class)
            ->targetRequiresPrivilegedConfirmation($linkedUser);
        $this->tempPasswordPrivilegedConfirm = false;
        $this->issuedTempPassword = '';
        $this->showTempPasswordResultModal = false;
        $this->showTempPasswordConfirmModal = true;
    }

    public function openTempPasswordModalFromEdit(): void
    {
        if ($this->editingEmpNo === '') {
            return;
        }

        $this->openTempPasswordModal($this->editingEmpNo);
    }

    public function closeTempPasswordConfirmModal(): void
    {
        $this->showTempPasswordConfirmModal = false;
        $this->tempPasswordTargetEmpNo = '';
        $this->tempPasswordTargetName = '';
        $this->tempPasswordTargetEmail = '';
        $this->tempPasswordPrivilegedConfirm = false;
        $this->tempPasswordTargetIsPrivileged = false;
    }

    public function issueTemporaryPassword(): void
    {
        Gate::authorize('manageUserAccounts');

        $employee = Employee::query()->where('EMPNO', $this->tempPasswordTargetEmpNo)->first();
        if (! $employee) {
            session()->flash('error', '대상 직원을 찾을 수 없습니다.');
            $this->closeTempPasswordConfirmModal();

            return;
        }

        $linkedUser = $this->resolveLinkedUser($employee);
        if ($linkedUser === null) {
            session()->flash('error', '연결된 로그인 계정이 없습니다.');
            $this->closeTempPasswordConfirmModal();

            return;
        }

        try {
            $plainPassword = app(SetTemporaryUserPassword::class)->execute(
                $linkedUser,
                auth()->user(),
                $this->tempPasswordPrivilegedConfirm,
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: '임시 비밀번호를 발급할 수 없습니다.';
            session()->flash('error', (string) $message);
            $this->closeTempPasswordConfirmModal();

            return;
        }

        $targetName = $this->tempPasswordTargetName;
        $this->showTempPasswordConfirmModal = false;
        $this->tempPasswordPrivilegedConfirm = false;
        $this->issuedTempPassword = $plainPassword;
        $this->showTempPasswordResultModal = true;
        session()->flash('success', ($targetName !== '' ? $targetName : '직원').'님에게 임시 비밀번호를 발급했습니다.');
    }

    public function closeTempPasswordResultModal(): void
    {
        $this->showTempPasswordResultModal = false;
        $this->issuedTempPassword = '';
        $this->tempPasswordTargetEmpNo = '';
        $this->tempPasswordTargetName = '';
        $this->tempPasswordTargetEmail = '';
        $this->tempPasswordTargetIsPrivileged = false;
    }

    /**
     * "계정 없음 + 재직" 직원에게 최소 권한으로 로그인 계정을 생성합니다.
     * 권한은 모두 false, 활성은 true, 비밀번호는 임시 난수.
     * 본인은 이후 메일 링크로 재설정합니다.
     */
    private function createMinimalLoginAccountFor(Employee $employee): void
    {
        $normalizedEmail = mb_strtolower(trim((string) ($employee->EMAIL ?? '')));
        $empNo = trim((string) ($employee->EMPNO ?? ''));

        if ($normalizedEmail === '' || $empNo === '') {
            throw ValidationException::withMessages([
                'resetTargetEmail' => ['직원 정보가 충분하지 않아 계정을 만들 수 없습니다.'],
            ]);
        }

        DB::transaction(function () use ($normalizedEmail, $empNo, $employee): void {
            $existingByEmail = User::query()
                ->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$normalizedEmail])
                ->lockForUpdate()
                ->first();

            if ($existingByEmail !== null) {
                $existingEmpNo = trim((string) ($existingByEmail->employee_empno ?? ''));
                if ($existingEmpNo !== '' && $existingEmpNo !== $empNo) {
                    throw ValidationException::withMessages([
                        'resetTargetEmail' => ['이미 다른 직원 계정에서 사용 중인 이메일입니다.'],
                    ]);
                }

                // 매우 드물게 이메일은 같은데 empno 연결만 빠져 있던 케이스 → 연결만 채워서 재사용
                $relinkPayload = [
                    'employee_empno' => $empNo,
                    'is_active' => true,
                    'is_coach_team_lead' => false,
                    'can_view_all_institutions' => false,
                ];
                $syncedTeam = TeamMenuContext::inferUserTeamForRegistration(
                    (string) ($employee->WORKDEPT ?? ''),
                    (string) ($employee->JOB ?? '')
                );
                if ($syncedTeam !== null) {
                    $relinkPayload['team'] = $syncedTeam;
                }
                $existingByEmail->forceFill($relinkPayload)->save();

                return;
            }

            $accountPayload = [
                'name' => trim((string) ($employee->KOREANAME ?? $normalizedEmail)),
                'email' => $normalizedEmail,
                'employee_empno' => $empNo,
                'password' => Str::random(48),
                'is_admin' => false,
                'is_gs_brochure_admin' => false,
                'can_manage_store_inventory' => false,
                'is_coach_team_lead' => false,
                'is_active' => true,
                'email_verified_at' => null,
            ];
            $syncedTeam = TeamMenuContext::inferUserTeamForRegistration(
                (string) ($employee->WORKDEPT ?? ''),
                (string) ($employee->JOB ?? '')
            );
            if ($syncedTeam !== null) {
                $accountPayload['team'] = $syncedTeam;
            }
            User::query()->create($accountPayload);
        });
    }

    /**
     * Password 브로커 상태 코드를 사용자 친화적 메시지로 변환해 flash 처리.
     */
    private function flashSendStatus(string $status): void
    {
        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('success', '비밀번호 재설정 메일을 발송했습니다.');

            return;
        }

        if ($status === Password::RESET_THROTTLED) {
            session()->flash('error', '잠시 후 다시 시도해 주세요. (같은 이메일에 1분 이내 재발송은 제한됩니다.)');

            return;
        }

        if ($status === Password::INVALID_USER) {
            session()->flash('error', '해당 이메일을 가진 로그인 계정을 찾을 수 없습니다.');

            return;
        }

        session()->flash('error', '메일 서버 인증 문제로 비밀번호 재설정 메일 발송에 실패했습니다. 메일 설정을 확인해 주세요.');
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

        $employees->getCollection()->transform(function ($employee) {
            $employee->DEPARTMENT_NAME = DepartmentDisplay::name(
                (string) ($employee->WORKDEPT ?? ''),
                (string) ($employee->DEPARTMENT_NAME ?? '')
            );

            return $employee;
        });

        $this->attachLinkedUserInfo($employees);

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
            'hasLastEmployeeImportRollback' => EmployeeImportRollback::hasPending(),
            'lastEmployeeImportRollbackSummary' => ($snapshot = EmployeeImportRollback::get()) !== null
                ? EmployeeImportRollback::summaryLabel($snapshot)
                : null,
            'isPeopleModalAccountEditEnabled' => (bool) config('features.people_modal_account_edit_enabled', true),
        ]);
    }

    private function resolveCurrentTeamLabel($deptOptions): string
    {
        if ($this->filterDept === '' && $this->filterStatus === '0') {
            return '비활성화 직원';
        }

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
            ->orderBy('DEPTNO')
            ->get()
            ->map(fn (Department $dept) => (object) [
                'WORKDEPT' => $dept->DEPTNO,
                'dept_name' => $dept->displayName(),
            ]);
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

    /**
     * 현재 페이지에 표시되는 직원 각각에 대해 연결된 로그인 계정 정보를
     * 동적 속성으로 부착합니다. 페이지당 20명에 대해 단일 whereIn 쿼리
     * 한 번만 실행하므로 N+1이 발생하지 않습니다.
     *
     * 매칭 기준: employee.EMPNO ↔ users.employee_empno (이메일 fallback은
     * 보안상 목록 노출 결정에는 사용하지 않고, 발송 시점에 resolveLinkedUser
     * 에서만 별도로 적용합니다.)
     */
    private function attachLinkedUserInfo(LengthAwarePaginator $employees): void
    {
        $empNos = collect($employees->items())
            ->pluck('EMPNO')
            ->filter(fn ($v): bool => trim((string) $v) !== '')
            ->map(fn ($v): string => trim((string) $v))
            ->unique()
            ->values()
            ->all();

        $linkedUsersByEmpNo = $empNos === []
            ? collect()
            : User::query()
                ->whereIn('employee_empno', $empNos)
                ->get(['id', 'employee_empno', 'is_active', 'email'])
                ->keyBy(fn (User $user): string => trim((string) $user->employee_empno));

        foreach ($employees->items() as $employee) {
            $linked = $linkedUsersByEmpNo->get(trim((string) ($employee->EMPNO ?? '')));
            $employee->linked_user_id = $linked?->id;
            $employee->linked_user_is_active = $linked ? (bool) $linked->is_active : null;
            $employee->linked_user_email = $linked?->email;
        }
    }

    private function resolveLinkedUser(Employee $employee): ?User
    {
        $employeeEmpNo = trim((string) ($employee->EMPNO ?? ''));
        $useAccountLink = (bool) config('features.people_use_account_link', true);

        if ($useAccountLink && $employeeEmpNo !== '') {
            $linkedByEmpNo = User::query()
                ->where('employee_empno', $employeeEmpNo)
                ->first($this->linkedUserSelectColumns());

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
            ->first($this->linkedUserSelectColumns());
    }

    /**
     * @return array<int, string>
     */
    private function linkedUserSelectColumns(): array
    {
        $columns = [
            'id',
            'email',
            'is_active',
            'is_admin',
            'is_deputy_admin',
            'is_gs_brochure_admin',
            'can_manage_store_inventory',
        ];

        if ($this->supportsCanViewAllInstitutionsColumn()) {
            $columns[] = 'can_view_all_institutions';
        }

        if ($this->supportsSetupPermissionColumns()) {
            $columns[] = 'setup_view';
            $columns[] = 'setup_manage';
        }

        return $columns;
    }

    private function supportsSetupPermissionColumns(): bool
    {
        if ($this->supportsSetupPermissionColumns !== null) {
            return $this->supportsSetupPermissionColumns;
        }

        $this->supportsSetupPermissionColumns = Schema::hasColumns('users', ['setup_view', 'setup_manage']);

        return $this->supportsSetupPermissionColumns;
    }

    private function supportsCanViewAllInstitutionsColumn(): bool
    {
        if ($this->supportsCanViewAllInstitutionsColumn !== null) {
            return $this->supportsCanViewAllInstitutionsColumn;
        }

        $this->supportsCanViewAllInstitutionsColumn = Schema::hasColumn('users', 'can_view_all_institutions');

        return $this->supportsCanViewAllInstitutionsColumn;
    }

    private function shouldActivateUserFromEmployeeStatus(?string $employeeStatus): bool
    {
        return (string) $employeeStatus !== '0';
    }

    private function forgetInstitutionManagerOptionCaches(): void
    {
        Cache::forget('institution-list:manager-options:'.TeamMenuContext::DEPT_CO);
        Cache::forget('institution-list:manager-options:'.TeamMenuContext::DEPT_COACH);
        Cache::forget('institution-list:manager-options:'.TeamMenuContext::DEPT_CS);
    }

    /**
     * 비밀번호 재설정 링크 발송. Laravel Password 브로커의 상태 코드를 그대로 반환합니다.
     *
     * 정상 동작 시: Password::RESET_LINK_SENT / INVALID_USER / RESET_THROTTLED
     * 메일 서버 등 외부 예외 발생 시: 'passwords.exception' (비표준, 명확한 분기를 위함)
     */
    private function sendResetLink(string $email): string
    {
        try {
            return Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            report($e);

            return 'passwords.exception';
        }
    }

    public function previewEmployeeImport(): void
    {
        Gate::authorize('manageEmployeeDepartment');

        $this->importNotice = null;
        $this->importErrors = [];

        $validated = $this->validate([
            'importFile' => ['required', 'file', 'mimes:xls,xlsx', 'max:20480'],
        ], [
            'importFile.required' => '업로드할 엑셀 파일을 선택해 주세요.',
            'importFile.mimes' => '엑셀 파일(xls, xlsx)만 업로드할 수 있습니다.',
            'importFile.max' => '파일 크기는 20MB 이하여야 합니다.',
        ]);

        /** @var TemporaryUploadedFile $file */
        $file = $validated['importFile'];
        $result = app(EmployeeExcelImporter::class)->importFromFile(
            $file->getRealPath(),
            (int) auth()->id(),
            dryRun: true,
        );

        $this->importPreview = $result;
        $this->importErrors = $result['errors'];
        $this->importNotice = sprintf(
            '미리보기: 신규 %d건, 수정 %d건, 재활성 %d건, 숨김 %d건, 신규 부서 %d건, 건너뜀 %d건',
            (int) $result['inserted'],
            (int) $result['updated'],
            (int) $result['reactivated'],
            (int) $result['hidden'],
            (int) $result['departments_created'],
            (int) $result['skipped'],
        );
    }

    public function applyEmployeeImport(): void
    {
        Gate::authorize('manageEmployeeDepartment');

        if ($this->importPreview === null) {
            $this->addError('importFile', '먼저 미리보기를 실행해 주세요.');

            return;
        }

        $validated = $this->validate([
            'importFile' => ['required', 'file', 'mimes:xls,xlsx', 'max:20480'],
        ], [
            'importFile.required' => '적용할 엑셀 파일을 다시 선택해 주세요.',
            'importFile.mimes' => '엑셀 파일(xls, xlsx)만 업로드할 수 있습니다.',
            'importFile.max' => '파일 크기는 20MB 이하여야 합니다.',
        ]);

        /** @var TemporaryUploadedFile $file */
        $file = $validated['importFile'];
        $result = app(EmployeeExcelImporter::class)->importFromFile(
            $file->getRealPath(),
            (int) auth()->id(),
            dryRun: false,
        );

        $this->importPreview = null;
        $this->importErrors = $result['errors'];
        $this->importFile = null;
        $this->resetPage();

        $summary = sprintf(
            '엑셀 반영 완료: 신규 %d건, 수정 %d건, 재활성 %d건, 숨김 %d건, 신규 부서 %d건, 건너뜀 %d건',
            (int) $result['inserted'],
            (int) $result['updated'],
            (int) $result['reactivated'],
            (int) $result['hidden'],
            (int) $result['departments_created'],
            (int) $result['skipped'],
        );

        if ((int) $result['reset_emails_sent'] > 0) {
            $summary .= ', 비밀번호 메일 '.(int) $result['reset_emails_sent'].'건 발송';
        }

        if ((int) $result['reset_emails_failed'] > 0) {
            $summary .= ', 메일 실패 '.(int) $result['reset_emails_failed'].'건';
        }

        $this->importNotice = $summary;
        session()->flash('success', $summary);

        if ((int) $result['reset_emails_failed'] > 0) {
            session()->flash('error', '일부 신규 계정의 비밀번호 설정 메일 발송에 실패했습니다. 메일 설정을 확인해 주세요.');
        }

        if (isset($result['rollback']) && is_array($result['rollback'])) {
            EmployeeImportRollback::save($result['rollback']);
        }
    }

    public function openImportResetModal(): void
    {
        Gate::authorize('manageEmployeeDepartment');

        if (! EmployeeImportRollback::hasPending()) {
            $this->addError('importResetConfirmationText', '되돌릴 마지막 엑셀 반영 기록이 없습니다.');

            return;
        }

        $this->resetValidation('importResetConfirmationText');
        $this->importResetConfirmationText = '';
        $this->showImportResetModal = true;
    }

    public function closeImportResetModal(): void
    {
        $this->showImportResetModal = false;
        $this->resetValidation('importResetConfirmationText');
        $this->importResetConfirmationText = '';
    }

    public function resetLastEmployeeImport(): void
    {
        Gate::authorize('manageEmployeeDepartment');

        $snapshot = EmployeeImportRollback::get();
        if ($snapshot === null || ! EmployeeImportRollback::hasChanges($snapshot)) {
            $this->addError('importResetConfirmationText', '되돌릴 마지막 엑셀 반영 기록이 없습니다.');

            return;
        }

        $this->validate([
            'importResetConfirmationText' => ['required', Rule::in([self::IMPORT_RESET_CONFIRMATION_PHRASE])],
        ], [
            'importResetConfirmationText.required' => '초기화 확인 문구를 입력해 주세요.',
            'importResetConfirmationText.in' => '확인 문구가 일치하지 않습니다. "엑셀 초기화"를 정확히 입력해 주세요.',
        ]);

        $result = app(EmployeeExcelImporter::class)->rollback($snapshot);

        EmployeeImportRollback::clear();

        $this->importResetConfirmationText = '';
        $this->showImportResetModal = false;
        $this->importPreview = null;
        $this->importFile = null;
        $this->importErrors = $result['errors'];
        $this->resetPage();

        $summary = sprintf(
            '엑셀 초기화 완료: 신규 삭제 %d건(계정 %d건), 수정 복원 %d건, 숨김 복원 %d건, 부서 삭제 %d건',
            (int) $result['deleted_employees'],
            (int) $result['deleted_users'],
            (int) $result['restored_updates'],
            (int) $result['restored_hidden'],
            (int) $result['deleted_departments'],
        );

        $this->importNotice = $summary;
        session()->flash('success', $summary);
    }

    public function cancelEmployeeImport(): void
    {
        Gate::authorize('manageEmployeeDepartment');

        $this->importPreview = null;
        $this->importNotice = null;
        $this->importErrors = [];
        $this->importFile = null;
        $this->resetValidation('importFile');
    }
}
