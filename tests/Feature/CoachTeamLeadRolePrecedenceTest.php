<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobTitlePermission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoachTeamLeadRolePrecedenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('employee')) {
            Schema::create('employee', function (Blueprint $table): void {
                $table->string('EMPNO')->primary();
                $table->string('JOB')->nullable();
                $table->string('WORKDEPT')->nullable();
                $table->integer('STATUS')->nullable();
            });
        }
    }

    public function test_sync_permissions_command_sets_coach_team_lead_from_matrix(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E001',
            'JOB' => 'Department Manager',
            'WORKDEPT' => 'A05',
            'STATUS' => 1,
        ]);

        JobTitlePermission::query()->create([
            'job_code' => 'Department Manager',
            'is_coach_team_lead' => true,
            'setup_view' => true,
        ]);

        $user = User::factory()->create([
            'employee_empno' => 'E001',
            'is_admin' => false,
            'is_coach_team_lead' => false,
        ]);

        $this->artisan('users:sync-permissions-from-job-titles')
            ->expectsOutputToContain('주의')
            ->assertSuccessful();

        $this->assertTrue((bool) $user->fresh()->is_coach_team_lead);
    }

    public function test_deprecated_coach_sync_command_delegates(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E001',
            'JOB' => 'Department Manager',
            'WORKDEPT' => 'A05',
            'STATUS' => 1,
        ]);

        JobTitlePermission::query()->create([
            'job_code' => 'Department Manager',
            'is_coach_team_lead' => true,
            'setup_view' => true,
        ]);

        $user = User::factory()->create([
            'employee_empno' => 'E001',
            'is_admin' => false,
            'is_coach_team_lead' => false,
        ]);

        $this->artisan('users:sync-coach-team-lead-from-jobs')
            ->expectsOutputToContain('deprecated')
            ->assertSuccessful();

        $this->assertTrue((bool) $user->fresh()->is_coach_team_lead);
    }
}
