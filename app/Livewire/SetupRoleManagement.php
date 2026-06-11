<?php

namespace App\Livewire;

use App\Models\AccountAuditLog;
use App\Models\SetupRole;
use App\Models\User;
use App\Support\CoachTeamLeadEligibility;
use App\Support\SetupRoleAccountFlags;
use App\Support\SetupRolePermissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class SetupRoleManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    public bool $showAssignModal = false;

    public int $assignRoleId = 0;

    public string $assignRoleName = '';

    public string $assignUserSearch = '';

    public string $assignUserId = '';

    public int $editId = 0;

    public int $deleteId = 0;

    public string $deleteRoleName = '';

    public string $newRoleKey = '';

    public string $newRoleName = '';

    public string $newDescription = '';

    public string $newIsActive = '1';

    public array $newPermissions = [];

    /** @var array<string, bool> */
    public array $newAccountFlags = [];

    public string $editRoleKey = '';

    public string $editRoleName = '';

    public string $editDescription = '';

    public string $editIsActive = '1';

    public array $editPermissions = [];

    /** @var array<string, bool> */
    public array $editAccountFlags = [];

    public array $permissionMenus = [
        SetupRolePermissions::MENU_SETUP => 'SetUp',
    ];

    public array $permissionActions = [];

    public function mount(): void
    {
        $this->permissionActions = SetupRolePermissions::actions();
        $this->newPermissions = SetupRolePermissions::defaultMatrix();
        $this->editPermissions = SetupRolePermissions::defaultMatrix();
        $this->newAccountFlags = SetupRoleAccountFlags::defaults();
        $this->editAccountFlags = SetupRoleAccountFlags::defaults();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedNewAccountFlagsIsAdmin($value): void
    {
        if ($value) {
            $this->newAccountFlags[SetupRoleAccountFlags::FLAG_IS_DEPUTY_ADMIN] = false;
            $this->newAccountFlags[SetupRoleAccountFlags::FLAG_IS_COACH_TEAM_LEAD] = false;
        }
    }

    public function updatedEditAccountFlagsIsAdmin($value): void
    {
        if ($value) {
            $this->editAccountFlags[SetupRoleAccountFlags::FLAG_IS_DEPUTY_ADMIN] = false;
            $this->editAccountFlags[SetupRoleAccountFlags::FLAG_IS_COACH_TEAM_LEAD] = false;
        }
    }

    public function updatedNewAccountFlagsIsDeputyAdmin($value): void
    {
        if ($value) {
            $this->newAccountFlags[SetupRoleAccountFlags::FLAG_IS_ADMIN] = false;
            $this->newAccountFlags[SetupRoleAccountFlags::FLAG_IS_COACH_TEAM_LEAD] = false;
        }
    }

    public function updatedEditAccountFlagsIsDeputyAdmin($value): void
    {
        if ($value) {
            $this->editAccountFlags[SetupRoleAccountFlags::FLAG_IS_ADMIN] = false;
            $this->editAccountFlags[SetupRoleAccountFlags::FLAG_IS_COACH_TEAM_LEAD] = false;
        }
    }

    public function openCreateModal(): void
    {
        Gate::authorize('manageTeamStructure');

        $this->newRoleKey = '';
        $this->newRoleName = '';
        $this->newDescription = '';
        $this->newIsActive = '1';
        $this->newPermissions = SetupRolePermissions::defaultMatrix();
        $this->newAccountFlags = SetupRoleAccountFlags::defaults();
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function createRole(): void
    {
        Gate::authorize('manageTeamStructure');

        $validated = $this->validate([
            'newRoleKey' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('setup_roles', 'role_key')],
            'newRoleName' => ['required', 'string', 'max:80'],
            'newDescription' => ['nullable', 'string', 'max:255'],
            'newIsActive' => ['required', 'in:0,1'],
        ], [
            'newRoleKey.required' => '역할 키는 필수입니다.',
            'newRoleKey.regex' => '역할 키는 영문 소문자, 숫자, 언더바만 사용해 주세요.',
            'newRoleKey.unique' => '이미 존재하는 역할 키입니다.',
            'newRoleName.required' => '역할명은 필수입니다.',
            'newRoleName.max' => '역할명은 80자 이하로 입력해 주세요.',
            'newIsActive.in' => '활성 여부 값이 올바르지 않습니다.',
        ]);

        SetupRole::query()->create([
            'role_key' => trim($validated['newRoleKey']),
            'role_name' => trim($validated['newRoleName']),
            'description' => trim((string) ($validated['newDescription'] ?? '')),
            'is_active' => $this->newIsActive === '1',
            'permissions' => SetupRolePermissions::normalizeMatrix($this->newPermissions),
            'account_flags' => SetupRoleAccountFlags::normalize($this->newAccountFlags),
        ]);

        $this->closeCreateModal();
        session()->flash('success', '역할이 생성되었습니다.');
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize('manageTeamStructure');

        $role = SetupRole::query()->find($id);
        if (! $role) {
            return;
        }

        $this->editId = $role->id;
        $this->editRoleKey = (string) $role->role_key;
        $this->editRoleName = (string) $role->role_name;
        $this->editDescription = (string) ($role->description ?? '');
        $this->editIsActive = $role->is_active ? '1' : '0';
        $this->editPermissions = SetupRolePermissions::normalizeMatrix($role->permissions ?? []);
        $this->editAccountFlags = SetupRoleAccountFlags::normalize($role->account_flags);
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editId = 0;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function updateRole(): void
    {
        Gate::authorize('manageTeamStructure');

        $validated = $this->validate([
            'editRoleKey' => [
                'required',
                'string',
                'max:40',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('setup_roles', 'role_key')->ignore($this->editId),
            ],
            'editRoleName' => ['required', 'string', 'max:80'],
            'editDescription' => ['nullable', 'string', 'max:255'],
            'editIsActive' => ['required', 'in:0,1'],
        ], [
            'editRoleKey.required' => '역할 키는 필수입니다.',
            'editRoleKey.regex' => '역할 키는 영문 소문자, 숫자, 언더바만 사용해 주세요.',
            'editRoleKey.unique' => '이미 존재하는 역할 키입니다.',
            'editRoleName.required' => '역할명은 필수입니다.',
            'editIsActive.in' => '활성 여부 값이 올바르지 않습니다.',
        ]);

        $role = SetupRole::query()->find($this->editId);
        if (! $role) {
            $this->addError('editRoleKey', '수정할 역할을 찾을 수 없습니다.');

            return;
        }

        $normalizedAccountFlags = SetupRoleAccountFlags::normalize($this->editAccountFlags);
        $this->guardSyncWouldRemoveLastAdmin($role, $normalizedAccountFlags);

        $role->role_key = trim($validated['editRoleKey']);
        $role->role_name = trim($validated['editRoleName']);
        $role->description = trim((string) ($validated['editDescription'] ?? ''));
        $role->is_active = $this->editIsActive === '1';
        $role->permissions = SetupRolePermissions::normalizeMatrix($this->editPermissions);
        $role->account_flags = $normalizedAccountFlags;
        $role->save();
        $role->syncAccountFlagsToAssignedUsers();

        $this->closeEditModal();
        session()->flash('success', '역할이 수정되었습니다.');
    }

    public function openDeleteModal(int $id): void
    {
        Gate::authorize('manageTeamStructure');

        $role = SetupRole::query()->find($id);
        if (! $role) {
            return;
        }

        $this->deleteId = $role->id;
        $this->deleteRoleName = (string) $role->role_name;
        $this->showDeleteModal = true;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deleteId = 0;
        $this->deleteRoleName = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function deleteRole(): void
    {
        Gate::authorize('manageTeamStructure');

        $role = SetupRole::query()->find($this->deleteId);
        if (! $role) {
            $this->addError('deleteId', '삭제할 역할을 찾을 수 없습니다.');

            return;
        }

        $role->delete();
        $this->closeDeleteModal();
        session()->flash('success', '역할이 삭제되었습니다.');
    }

    public function openAssignModal(int $id): void
    {
        Gate::authorize('manageTeamStructure');

        $role = SetupRole::query()->find($id);
        if (! $role) {
            return;
        }

        $this->assignRoleId = $role->id;
        $this->assignRoleName = (string) $role->role_name;
        $this->assignUserSearch = '';
        $this->assignUserId = '';
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showAssignModal = true;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->assignRoleId = 0;
        $this->assignRoleName = '';
        $this->assignUserSearch = '';
        $this->assignUserId = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function assignUserToRole(): void
    {
        Gate::authorize('manageTeamStructure');

        $validated = $this->validate([
            'assignUserId' => ['required', 'integer', 'exists:users,id'],
        ], [
            'assignUserId.required' => '할당할 사용자를 선택해 주세요.',
            'assignUserId.exists' => '선택한 사용자를 찾을 수 없습니다.',
        ]);

        $role = SetupRole::query()->find($this->assignRoleId);
        if (! $role || ! $role->is_active) {
            $this->addError('assignUserId', '활성 역할을 찾을 수 없습니다.');

            return;
        }

        $user = User::query()->with('employee')->find((int) $validated['assignUserId']);
        if (! $user) {
            $this->addError('assignUserId', '선택한 사용자를 찾을 수 없습니다.');

            return;
        }

        if ($user->setup_role_id === $role->id) {
            $this->addError('assignUserId', '이미 이 역할이 할당된 사용자입니다.');

            return;
        }

        $flags = $role->normalizedAccountFlags();

        if ($this->wouldRemoveLastActiveAdmin($user, $flags)) {
            throw ValidationException::withMessages([
                'assignUserId' => ['마지막 활성 관리자 계정은 관리자 권한을 해제할 수 없습니다.'],
            ]);
        }

        $this->guardCoachTeamLeadAssignment($user, $flags);

        $beforeRoleId = $user->setup_role_id;
        $actor = auth()->user();

        $user->setup_role_id = $role->id;
        SetupRoleAccountFlags::applyToUser($user, $flags);
        $user->save();

        AccountAuditLog::record($user, $actor, 'role_changed', [
            'setup_role_id' => [
                'before' => $beforeRoleId,
                'after' => $role->id,
            ],
        ]);

        $this->assignUserId = '';
        session()->flash('success', '사용자에게 역할이 할당되었습니다.');
    }

    public function removeUserFromRole(int $userId): void
    {
        Gate::authorize('manageTeamStructure');

        $role = SetupRole::query()->find($this->assignRoleId);
        if (! $role) {
            return;
        }

        $user = User::query()
            ->whereKey($userId)
            ->where('setup_role_id', $role->id)
            ->first();

        if (! $user) {
            return;
        }

        $defaults = SetupRoleAccountFlags::defaults();

        if ($this->wouldRemoveLastActiveAdmin($user, $defaults)) {
            throw ValidationException::withMessages([
                'assignUserId' => ['마지막 활성 관리자 계정은 관리자 권한을 해제할 수 없습니다.'],
            ]);
        }

        $beforeRoleId = $user->setup_role_id;
        $actor = auth()->user();

        $user->setup_role_id = null;
        SetupRoleAccountFlags::applyToUser($user, $defaults);
        $user->save();

        AccountAuditLog::record($user, $actor, 'role_changed', [
            'setup_role_id' => [
                'before' => $beforeRoleId,
                'after' => null,
            ],
        ]);

        session()->flash('success', '역할 할당이 해제되었습니다.');
    }

    public function render(): View
    {
        $roles = SetupRole::query()
            ->withCount('users')
            ->when(trim($this->search) !== '', function ($q) {
                $keyword = preg_replace('/\s+/u', '', trim($this->search)) ?? '';
                if ($keyword === '') {
                    return;
                }

                $q->where(function ($sub) use ($keyword) {
                    $sub->whereRaw("REPLACE(role_key, ' ', '') like ?", ["%{$keyword}%"])
                        ->orWhereRaw("REPLACE(role_name, ' ', '') like ?", ["%{$keyword}%"]);
                });
            })
            ->orderBy('id')
            ->paginate(15);

        $assignedUsers = collect();
        $assignableUsers = collect();

        if ($this->showAssignModal && $this->assignRoleId > 0) {
            $assignedUsers = User::query()
                ->where('setup_role_id', $this->assignRoleId)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'employee_empno']);

            $keyword = preg_replace('/\s+/u', '', trim($this->assignUserSearch)) ?? '';

            $assignableUsers = User::query()
                ->where('is_active', true)
                ->when($keyword !== '', function ($query) use ($keyword): void {
                    $query->where(function ($sub) use ($keyword): void {
                        $sub->whereRaw("REPLACE(name, ' ', '') like ?", ["%{$keyword}%"])
                            ->orWhereRaw("REPLACE(email, ' ', '') like ?", ["%{$keyword}%"])
                            ->orWhereRaw("REPLACE(COALESCE(employee_empno, ''), ' ', '') like ?", ["%{$keyword}%"]);
                    });
                })
                ->with('setupRole:id,role_name')
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'email', 'employee_empno', 'setup_role_id']);
        }

        return view('livewire.setup-role-management', [
            'roles' => $roles,
            'assignedUsers' => $assignedUsers,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    /**
     * @param  array<string, bool>  $newFlags
     */
    private function wouldRemoveLastActiveAdmin(User $user, array $newFlags): bool
    {
        $isCurrentlyActiveAdmin = (bool) $user->is_active && (bool) $user->is_admin;
        $willRemainActiveAdmin = (bool) $user->is_active
            && (bool) ($newFlags[SetupRoleAccountFlags::FLAG_IS_ADMIN] ?? false);

        if (! $isCurrentlyActiveAdmin || $willRemainActiveAdmin) {
            return false;
        }

        return User::query()
            ->where('is_active', true)
            ->where('is_admin', true)
            ->whereKeyNot($user->id)
            ->count() === 0;
    }

    /**
     * @param  array<string, bool>  $normalizedAccountFlags
     */
    private function guardSyncWouldRemoveLastAdmin(SetupRole $role, array $normalizedAccountFlags): void
    {
        if ($normalizedAccountFlags[SetupRoleAccountFlags::FLAG_IS_ADMIN]) {
            return;
        }

        $losingAdminUserIds = $role->users()
            ->where('is_active', true)
            ->where('is_admin', true)
            ->pluck('id')
            ->all();

        if ($losingAdminUserIds === []) {
            return;
        }

        $remainingActiveAdminCount = User::query()
            ->where('is_active', true)
            ->where('is_admin', true)
            ->whereNotIn('id', $losingAdminUserIds)
            ->count();

        if ($remainingActiveAdminCount === 0) {
            throw ValidationException::withMessages([
                'editAccountFlags.is_admin' => ['마지막 활성 관리자 계정은 관리자 권한을 해제할 수 없습니다.'],
            ]);
        }
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function guardCoachTeamLeadAssignment(User $user, array $flags): void
    {
        $roleGrantsCoachKpi = (bool) ($flags[SetupRoleAccountFlags::FLAG_IS_COACH_TEAM_LEAD] ?? false);
        $roleGrantsAdmin = (bool) ($flags[SetupRoleAccountFlags::FLAG_IS_ADMIN] ?? false);
        $roleGrantsDeputy = (bool) ($flags[SetupRoleAccountFlags::FLAG_IS_DEPUTY_ADMIN] ?? false);

        if (! $roleGrantsCoachKpi || $roleGrantsAdmin || $roleGrantsDeputy) {
            return;
        }

        $employee = $user->employee;

        if (! CoachTeamLeadEligibility::allowsTeamKpiCheckbox(
            true,
            (string) ($employee->JOB ?? ''),
            (string) ($employee->WORKDEPT ?? ''),
            isset($employee->STATUS) ? (int) $employee->STATUS : null,
        )) {
            throw ValidationException::withMessages([
                'assignUserId' => ['팀 지원 KPI 권한은 Coach 부서(A05)의 Department Manager(재직)에게만 부여할 수 있습니다.'],
            ]);
        }
    }
}
