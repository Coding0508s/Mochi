<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SetupPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // 레거시 HR 테이블은 마이그레이션에 없어, Setup 팀 화면 스모크용 최소 스키마만 보강합니다.
        if (! Schema::hasTable('department')) {
            Schema::create('department', function (Blueprint $table): void {
                $table->string('DEPTNO')->primary();
                $table->string('DEPTNAME')->nullable();
                $table->string('ADMRDEPT')->nullable();
                $table->string('LOCATION')->nullable();
            });
        }

        if (! Schema::hasTable('employee')) {
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
    }

    public function test_setup_hub_returns_ok(): void
    {
        $this->actingAs($this->user)->get('/setup')->assertOk();
    }

    public function test_setup_team_returns_ok(): void
    {
        $this->actingAs($this->user)->get('/setup/team')->assertOk();
    }

    public function test_setup_common_codes_returns_ok(): void
    {
        $this->actingAs($this->user)->get('/setup/common-codes')->assertOk();
    }

    public function test_setup_roles_returns_ok(): void
    {
        $this->actingAs($this->user)->get('/setup/roles')->assertOk();
    }

    public function test_setup_employee_create_allows_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/setup/employees/create')->assertOk();
    }

    public function test_setup_employee_create_forbids_non_admin(): void
    {
        $this->actingAs($this->user)->get('/setup/employees/create')->assertForbidden();
    }

    public function test_setup_named_route_urls(): void
    {
        $this->assertStringEndsWith('/setup', route('setup.index', [], false));
        $this->assertStringEndsWith('/setup/team', route('setup.team', [], false));
        $this->assertStringEndsWith('/setup/common-codes', route('setup.common-codes', [], false));
        $this->assertStringEndsWith('/setup/roles', route('setup.roles', [], false));
        $this->assertStringEndsWith('/setup/employees/create', route('setup.employees.create', [], false));
    }
}
