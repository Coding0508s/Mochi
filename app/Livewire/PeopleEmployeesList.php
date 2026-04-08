<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $employee = Employee::query()->where('EMPNO', $empNo)->first();
        if (!$employee) {
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

        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function saveEmployee(): void
    {
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

        $employee = Employee::query()->where('EMPNO', $this->editingEmpNo)->first();
        if (!$employee) {
            $this->addError('editKoreanName', '수정 대상 직원을 찾을 수 없습니다.');
            return;
        }

        $employee->KOREANAME = trim($validated['editKoreanName']);
        $employee->ENGLISHNAME = trim($validated['editEnglishName']);
        $employee->JOB = trim($validated['editJob']);
        $employee->EMAIL = trim($validated['editEmail']);
        $employee->PHONENO = trim($validated['editPhone']);
        $employee->WORKDEPT = $validated['editWorkDept'];
        $employee->STATUS = $validated['editStatus'] === '' ? null : (int) $validated['editStatus'];
        $employee->save();

        $this->closeEditModal();
        session()->flash('success', '직원 정보가 저장되었습니다.');
    }

    public function openCreateTeamModal(): void
    {
        $this->newDeptName = '';
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showCreateTeamModal = true;
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

        if (!$deleted) {
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
        ]);
    }

    private function resolveCurrentTeamLabel($deptOptions): string
    {
        if ($this->filterDept === '') {
            return '전체';
        }

        $matched = $deptOptions->firstWhere('WORKDEPT', $this->filterDept);
        if (!$matched) {
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

    private function nextDeptNo(): string
    {
        $maxNumber = (int) (Department::query()
            ->whereRaw("DEPTNO REGEXP '^A[0-9]{2,}$'")
            ->selectRaw('MAX(CAST(SUBSTRING(DEPTNO, 2) AS UNSIGNED)) as max_number')
            ->value('max_number') ?? 0);

        $nextNumber = $maxNumber + 1;

        return 'A' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
    }
}

