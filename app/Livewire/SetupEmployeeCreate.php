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

class SetupEmployeeCreate extends Component
{
    public string $empNo = '';

    public string $koreanName = '';

    public string $englishName = '';

    public string $job = '';

    public string $email = '';

    public string $phone = '';

    public string $workDept = '';

    public string $status = '1';

    public ?string $hireDate = null;

    public bool $issueLoginAccount = false;

    public bool $isGsBrochureAdmin = false;

    public function mount(): void
    {
        Gate::authorize('manageEmployeeDepartment');
    }

    public function updatedIssueLoginAccount($value): void
    {
        if (! (bool) $value) {
            $this->isGsBrochureAdmin = false;
        }
    }

    public function save(): void
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

        $emailRules = ['required', 'email', 'max:100'];
        if ($this->issueLoginAccount) {
            $emailRules[] = Rule::unique('users', 'email');
        }

        $validated = $this->validate([
            'empNo' => ['required', 'string', 'max:20', Rule::unique('employee', 'EMPNO')],
            'koreanName' => ['required', 'string', 'max:20'],
            'englishName' => ['required', 'string', 'max:50'],
            'job' => $jobRules,
            'email' => $emailRules,
            'phone' => ['required', 'string', 'max:20'],
            'status' => ['nullable', 'in:0,1'],
            'workDept' => ['required', 'string', Rule::in($deptCodes)],
            'hireDate' => ['nullable', 'date'],
            'issueLoginAccount' => ['boolean'],
            'isGsBrochureAdmin' => ['boolean'],
        ], [
            'empNo.required' => '사번은 필수입니다.',
            'empNo.unique' => '이미 등록된 사번입니다.',
            'koreanName.required' => '이름(한글)은 필수입니다.',
            'englishName.required' => '영어 이름은 필수입니다.',
            'job.required' => '직책은 필수입니다.',
            'email.required' => '이메일은 필수입니다.',
            'email.email' => '이메일 형식이 올바르지 않습니다.',
            'email.unique' => '이미 로그인 계정이 있는 이메일입니다. 다른 이메일을 쓰거나 계정 발급을 해제하세요.',
            'phone.required' => '연락처는 필수입니다.',
            'workDept.required' => '부서는 필수입니다.',
            'workDept.in' => '선택 가능한 부서를 선택해 주세요.',
            'status.in' => '상태 값이 올바르지 않습니다.',
            'job.in' => '직책은 목록에서 선택해 주세요.',
        ]);

        $email = strtolower(trim($validated['email']));

        DB::transaction(function () use ($validated, $email): void {
            Employee::query()->create([
                'EMPNO' => trim($validated['empNo']),
                'KOREANAME' => trim($validated['koreanName']),
                'ENGLISHNAME' => trim($validated['englishName']),
                'JOB' => trim($validated['job']),
                'EMAIL' => $email,
                'PHONENO' => trim($validated['phone']),
                'WORKDEPT' => $validated['workDept'],
                'STATUS' => $validated['status'] === '' || $validated['status'] === null ? null : (int) $validated['status'],
                'HIREDATE' => $validated['hireDate'] ?? null,
            ]);

            if (! $this->issueLoginAccount) {
                return;
            }

            User::query()->create([
                'name' => trim($validated['koreanName']),
                'email' => $email,
                'password' => Str::random(48),
                'is_admin' => false,
                'is_gs_brochure_admin' => (bool) ($validated['isGsBrochureAdmin'] ?? false),
                'email_verified_at' => null,
            ]);

            $status = Password::sendResetLink(['email' => $email]);

            if ($status !== Password::RESET_LINK_SENT) {
                throw ValidationException::withMessages([
                    'email' => [__($status)],
                ]);
            }
        });

        if ($this->issueLoginAccount) {
            session()->flash('success', '신규 직원이 등록되었고, 로그인 비밀번호 설정 안내 메일을 발송했습니다.');
        } else {
            session()->flash('success', '신규 직원이 등록되었습니다.');
        }

        $this->redirect(route('people.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.setup-employee-create', [
            'deptOptions' => $this->getDeptOptions(),
            'jobOptions' => $this->getJobOptions(),
        ]);
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
}
