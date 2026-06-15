<?php

namespace Tests\Feature;

use App\Models\SetupRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachTeamLeadRolePrecedenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_skips_users_with_setup_role(): void
    {
        $role = SetupRole::query()->create([
            'role_key' => 'standard',
            'role_name' => '일반',
            'description' => '',
            'is_active' => true,
            'permissions' => [],
            'account_flags' => [],
        ]);

        $userWithRole = User::factory()->create([
            'setup_role_id' => $role->id,
            'employee_empno' => 'E001',
            'is_coach_team_lead' => false,
        ]);

        $this->artisan('users:sync-coach-team-lead-from-jobs')
            ->assertSuccessful();

        $userWithRole->refresh();

        $this->assertFalse((bool) $userWithRole->is_coach_team_lead);
    }
}
