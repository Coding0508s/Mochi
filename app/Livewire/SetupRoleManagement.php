<?php

namespace App\Livewire;

use App\Models\SetupRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class SetupRoleManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    public int $editId = 0;

    public int $deleteId = 0;

    public string $deleteRoleName = '';

    public string $newRoleKey = '';

    public string $newRoleName = '';

    public string $newDescription = '';

    public string $newIsActive = '1';

    public array $newPermissions = [];

    public string $editRoleKey = '';

    public string $editRoleName = '';

    public string $editDescription = '';

    public string $editIsActive = '1';

    public array $editPermissions = [];

    public array $permissionMenus = [
        'people' => 'People',
        'institutions' => '기관 리스트',
        'contacts' => '기관 연락처',
        'supports' => '기관 지원 내역',
        'potential_institutions' => '잠재기관 관리',
        'setup' => 'SetUp',
    ];

    public array $permissionActions = ['view', 'create', 'update', 'delete'];

    public function mount(): void
    {
        $this->newPermissions = $this->defaultPermissions();
        $this->editPermissions = $this->defaultPermissions();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('manageTeamStructure');

        $this->newRoleKey = '';
        $this->newRoleName = '';
        $this->newDescription = '';
        $this->newIsActive = '1';
        $this->newPermissions = $this->defaultPermissions();
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
            'permissions' => $this->normalizePermissions($this->newPermissions),
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
        $this->editPermissions = $this->mergePermissions($role->permissions ?? []);
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

        $role->role_key = trim($validated['editRoleKey']);
        $role->role_name = trim($validated['editRoleName']);
        $role->description = trim((string) ($validated['editDescription'] ?? ''));
        $role->is_active = $this->editIsActive === '1';
        $role->permissions = $this->normalizePermissions($this->editPermissions);
        $role->save();

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

    public function render()
    {
        $roles = SetupRole::query()
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

        return view('livewire.setup-role-management', [
            'roles' => $roles,
        ]);
    }

    private function defaultPermissions(): array
    {
        $permissions = [];

        foreach (array_keys($this->permissionMenus) as $menuKey) {
            $permissions[$menuKey] = [];

            foreach ($this->permissionActions as $action) {
                $permissions[$menuKey][$action] = false;
            }
        }

        return $permissions;
    }

    private function mergePermissions(array $savedPermissions): array
    {
        $merged = $this->defaultPermissions();

        foreach ($merged as $menuKey => $actions) {
            foreach (array_keys($actions) as $action) {
                $merged[$menuKey][$action] = (bool) ($savedPermissions[$menuKey][$action] ?? false);
            }
        }

        return $merged;
    }

    private function normalizePermissions(array $permissions): array
    {
        $normalized = $this->defaultPermissions();

        foreach ($normalized as $menuKey => $actions) {
            foreach (array_keys($actions) as $action) {
                $normalized[$menuKey][$action] = (bool) ($permissions[$menuKey][$action] ?? false);
            }
        }

        return $normalized;
    }
}
