<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\EmployeeSex;
use App\Support\TeamMenuContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public string $sex = '';

    public function mount(): void
    {
        Gate::authorize('manageEmployeeDepartment');
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

        $emailRules = ['required', 'email', 'max:100', Rule::unique('users', 'email')];

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
            'sex' => ['nullable', 'string', Rule::in(EmployeeSex::allowedValues())],
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
            'sex.in' => '성별 값이 올바르지 않습니다.',
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
                'SEX' => EmployeeSex::normalizeForStorage($validated['sex'] ?? null),
            ]);

            $userPayload = [
                'name' => trim($validated['koreanName']),
                'email' => $email,
                'employee_empno' => trim($validated['empNo']),
                'password' => Str::random(48),
                'is_admin' => false,
                'is_gs_brochure_admin' => false,
                'can_manage_store_inventory' => false,
                'is_coach_team_lead' => false,
                'is_deputy_admin' => false,
                'is_active' => true,
                'email_verified_at' => null,
            ];
            $inferredTeam = TeamMenuContext::inferUserTeamForRegistration(
                $validated['workDept'],
                trim($validated['job'])
            );
            if ($inferredTeam !== null) {
                $userPayload['team'] = $inferredTeam;
            }
            User::query()->create($userPayload);
        });

        $resetLinkSent = $this->sendResetLinkSafely($email);
        if ($resetLinkSent) {
            session()->flash('success', '신규 직원이 등록되었고, 로그인 비밀번호 설정 안내 메일을 발송했습니다.');
        } else {
            session()->flash('success', '신규 직원과 로그인 계정이 생성되었습니다.');
            session()->flash('error', '메일 서버 인증 문제로 비밀번호 설정 메일 발송에 실패했습니다. 메일 설정을 확인해 주세요.');
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
}
