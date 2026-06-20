<?php

namespace Tests\Feature;

use App\Models\Employee;
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

    public function test_sync_command_updates_eligible_users_regardless_of_previous_role_assignment(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E001',
            'JOB' => 'Department Manager',
            'WORKDEPT' => 'A05',
            'STATUS' => 1,
        ]);

        $user = User::factory()->create([
            'employee_empno' => 'E001',
            'is_coach_team_lead' => false,
        ]);

        $this->artisan('users:sync-coach-team-lead-from-jobs')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue((bool) $user->is_coach_team_lead);
    }
}
