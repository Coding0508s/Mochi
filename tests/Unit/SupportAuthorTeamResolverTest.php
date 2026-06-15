<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\User;
use App\Support\SupportAuthorTeamResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupportAuthorTeamResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmployeeTable();
    }

    private function createEmployeeTable(): void
    {
        Schema::dropIfExists('employee');

        Schema::create('employee', function (Blueprint $table): void {
            $table->string('EMPNO')->primary();
            $table->string('WORKDEPT')->nullable();
            $table->string('KOREANAME')->nullable();
            $table->string('ENGLISHNAME')->nullable();
            $table->string('JOB')->nullable();
            $table->string('EMAIL')->nullable();
            $table->string('PHONENO')->nullable();
            $table->integer('STATUS')->nullable();
            $table->date('HIREDATE')->nullable();
        });
    }

    public function test_resolves_team_from_employee_workdept(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E001',
            'KOREANAME' => '김코치',
            'WORKDEPT' => 'A05',
        ]);

        Employee::query()->create([
            'EMPNO' => 'E002',
            'KOREANAME' => '이씨에스',
            'WORKDEPT' => 'A03',
        ]);

        Employee::query()->create([
            'EMPNO' => 'E003',
            'KOREANAME' => '박컨설',
            'WORKDEPT' => 'A02',
        ]);

        $resolver = new SupportAuthorTeamResolver;

        $this->assertSame(SupportAuthorTeamResolver::TEAM_COACH, $resolver->resolve('김코치'));
        $this->assertSame(SupportAuthorTeamResolver::TEAM_CS, $resolver->resolve('이씨에스'));
        $this->assertSame(SupportAuthorTeamResolver::TEAM_CO, $resolver->resolve('박컨설'));
    }

    public function test_resolves_team_from_user_when_employee_missing(): void
    {
        User::factory()->create([
            'name' => 'User Coach',
            'team' => 'COACH',
        ]);

        $resolver = new SupportAuthorTeamResolver;

        $this->assertSame(SupportAuthorTeamResolver::TEAM_COACH, $resolver->resolve('User Coach'));
    }

    public function test_resolves_team_from_employee_job_when_workdept_empty(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E010',
            'KOREANAME' => '직무코치',
            'WORKDEPT' => '',
            'JOB' => 'Coach',
        ]);

        $resolver = new SupportAuthorTeamResolver;

        $this->assertSame(SupportAuthorTeamResolver::TEAM_COACH, $resolver->resolve('직무코치'));
    }

    public function test_returns_unknown_for_unmatched_author(): void
    {
        $resolver = new SupportAuthorTeamResolver;

        $this->assertSame(SupportAuthorTeamResolver::TEAM_UNKNOWN, $resolver->resolve('알수없는작성자'));
        $this->assertSame(SupportAuthorTeamResolver::TEAM_UNKNOWN, $resolver->resolve(''));
    }

    public function test_employee_table_is_not_queried_per_user(): void
    {
        // employee 와 연결된 유저를 여러 명 만든다. (이전엔 유저마다 nameForCoReports() 가
        // employee 를 재조회해 N+1 이 발생했다)
        for ($i = 1; $i <= 8; $i++) {
            $empno = 'U'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            Employee::query()->create([
                'EMPNO' => $empno,
                'KOREANAME' => '직원'.$i,
                'ENGLISHNAME' => 'Staff '.$i,
                'EMAIL' => 'staff'.$i.'@example.com',
                'WORKDEPT' => 'A05',
            ]);
            User::factory()->create([
                'name' => 'Staff '.$i,
                'email' => 'staff'.$i.'@example.com',
                'employee_empno' => $empno,
                'team' => 'COACH',
            ]);
        }

        $employeeDataQueries = 0;
        DB::listen(function ($query) use (&$employeeDataQueries): void {
            // 스키마 조회(sqlite_master/pragma)는 제외하고 실제 employee 데이터 SELECT 만 집계
            if (str_contains($query->sql, 'from "employee"')) {
                $employeeDataQueries++;
            }
        });

        $resolver = new SupportAuthorTeamResolver;
        // 인덱스 로드는 1회만 일어나야 한다 — 여러 번 resolve 해도 추가 employee 쿼리는 없어야 함.
        $this->assertSame(SupportAuthorTeamResolver::TEAM_COACH, $resolver->resolve('Staff 1'));
        $resolver->resolve('Staff 5');
        $resolver->resolve('Staff 8');

        // employee 1회(전량 적재) + users 의 with(employee) eager 1회 = 상수 2회 이하.
        // 유저 수(8)에 비례해 늘어나면(N+1) 실패한다.
        $this->assertLessThanOrEqual(
            2,
            $employeeDataQueries,
            "employee 테이블이 유저 수만큼 반복 조회되면 안 된다 (실제: {$employeeDataQueries}회)",
        );
    }
}
