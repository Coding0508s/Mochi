<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\User;
use App\Support\SupportAuthorTeamResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
