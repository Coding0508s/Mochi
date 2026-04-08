<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class SetupTeamManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public string $newDeptName = '';
    public string $newAdmrDept = '';
    public string $newLocation = '';

    public string $editDeptNo = '';
    public string $editDeptName = '';
    public string $editAdmrDept = '';
    public string $editLocation = '';

    public string $deleteDeptNo = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->newDeptName = '';
        $this->newAdmrDept = '';
        $this->newLocation = '';
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

    public function createTeam(): void
    {
        $parentCodes = $this->parentDeptCodes();

        $validated = $this->validate([
            'newDeptName' => ['required', 'string', 'max:25'],
            'newAdmrDept' => ['nullable', 'string', Rule::in($parentCodes)],
            'newLocation' => ['nullable', 'string', 'max:50'],
        ], [
            'newDeptName.required' => '팀명은 필수입니다.',
            'newDeptName.max' => '팀명은 25자 이하로 입력해 주세요.',
            'newAdmrDept.in' => '상위 부서 값이 올바르지 않습니다.',
            'newLocation.max' => '위치는 50자 이하로 입력해 주세요.',
        ]);

        $newDeptNo = $this->nextDeptNo();

        Department::query()->create([
            'DEPTNO' => $newDeptNo,
            'DEPTNAME' => trim($validated['newDeptName']),
            'MGRNO' => '',
            'ADMRDEPT' => $validated['newAdmrDept'] ?? '',
            'LOCATION' => trim((string) ($validated['newLocation'] ?? '')),
        ]);

        $this->closeCreateModal();
        session()->flash('success', "새 팀({$newDeptNo})이 생성되었습니다.");
    }

    public function openEditModal(string $deptNo): void
    {
        $team = Department::query()->where('DEPTNO', $deptNo)->first();
        if (!$team) {
            return;
        }

        $this->editDeptNo = (string) $team->DEPTNO;
        $this->editDeptName = (string) ($team->DEPTNAME ?? '');
        $this->editAdmrDept = (string) ($team->ADMRDEPT ?? '');
        $this->editLocation = (string) ($team->LOCATION ?? '');
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editDeptNo = '';
        $this->editDeptName = '';
        $this->editAdmrDept = '';
        $this->editLocation = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function updateTeam(): void
    {
        $parentCodes = $this->parentDeptCodes();

        $validated = $this->validate([
            'editDeptName' => ['required', 'string', 'max:25'],
            'editAdmrDept' => ['nullable', 'string', Rule::in($parentCodes)],
            'editLocation' => ['nullable', 'string', 'max:50'],
        ], [
            'editDeptName.required' => '팀명은 필수입니다.',
            'editDeptName.max' => '팀명은 25자 이하로 입력해 주세요.',
            'editAdmrDept.in' => '상위 부서 값이 올바르지 않습니다.',
            'editLocation.max' => '위치는 50자 이하로 입력해 주세요.',
        ]);

        $team = Department::query()->where('DEPTNO', $this->editDeptNo)->first();
        if (!$team) {
            $this->addError('editDeptName', '수정할 팀을 찾을 수 없습니다.');
            return;
        }

        $team->DEPTNAME = trim($validated['editDeptName']);
        $team->ADMRDEPT = $validated['editAdmrDept'] ?? '';
        $team->LOCATION = trim((string) ($validated['editLocation'] ?? ''));
        $team->save();

        $this->closeEditModal();
        session()->flash('success', "팀({$team->DEPTNO}) 정보가 수정되었습니다.");
    }

    public function openDeleteModal(string $deptNo): void
    {
        $this->deleteDeptNo = $deptNo;
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deleteDeptNo = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function deleteTeam(): void
    {
        $validated = $this->validate([
            'deleteDeptNo' => ['required', 'string', Rule::exists('department', 'DEPTNO')],
        ], [
            'deleteDeptNo.required' => '삭제할 팀이 지정되지 않았습니다.',
            'deleteDeptNo.exists' => '삭제 대상 팀을 찾을 수 없습니다.',
        ]);

        $deptNo = (string) $validated['deleteDeptNo'];
        $employeeCount = Employee::query()->where('WORKDEPT', $deptNo)->count();

        if ($employeeCount > 0) {
            $this->addError('deleteDeptNo', "소속 직원 {$employeeCount}명이 있어 삭제할 수 없습니다.");
            return;
        }

        Department::query()->where('DEPTNO', $deptNo)->delete();
        $this->closeDeleteModal();
        session()->flash('success', "팀({$deptNo})이 삭제되었습니다.");
    }

    public function render()
    {
        $teams = Department::query()
            ->select('department.*')
            ->selectSub(
                Employee::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('WORKDEPT', 'department.DEPTNO'),
                'employee_count'
            )
            ->when(trim($this->search) !== '', function ($q) {
                $keyword = preg_replace('/\s+/u', '', trim($this->search)) ?? '';
                if ($keyword === '') {
                    return;
                }

                $q->where(function ($sub) use ($keyword) {
                    $sub->whereRaw("REPLACE(DEPTNO, ' ', '') like ?", ["%{$keyword}%"])
                        ->orWhereRaw("REPLACE(DEPTNAME, ' ', '') like ?", ["%{$keyword}%"]);
                });
            })
            ->orderBy('DEPTNO')
            ->paginate(15);

        $parentOptions = Department::query()
            ->select('DEPTNO', 'DEPTNAME')
            ->orderBy('DEPTNO')
            ->get();

        return view('livewire.setup-team-management', [
            'teams' => $teams,
            'parentOptions' => $parentOptions,
        ]);
    }

    private function nextDeptNo(): string
    {
        $maxNumber = (int) (Department::query()
            ->whereRaw("DEPTNO REGEXP '^A[0-9]{2,}$'")
            ->selectRaw('MAX(CAST(SUBSTRING(DEPTNO, 2) AS UNSIGNED)) as max_number')
            ->value('max_number') ?? 0);

        return 'A' . str_pad((string) ($maxNumber + 1), 2, '0', STR_PAD_LEFT);
    }

    private function parentDeptCodes(): array
    {
        return Department::query()
            ->pluck('DEPTNO')
            ->map(fn ($code) => (string) $code)
            ->all();
    }
}

