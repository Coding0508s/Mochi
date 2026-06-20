<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SetupRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_edit_remains_admin_only(): void
    {
        $user = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => true,
        ]);

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('editEmployeeProfile'));
    }

    public function test_delete_gates_remain_admin_only(): void
    {
        $user = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => true,
        ]);

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('deleteContactRecords'));
    }

    public function test_deputy_admin_can_view_setup_but_not_manage(): void
    {
        $deputy = User::factory()->deputyAdmin()->create();

        $this->actingAs($deputy);
        $this->assertTrue(Gate::allows('accessSetup'));
        $this->assertFalse(Gate::allows('manageTeamStructure'));
        $this->assertFalse(Gate::allows('deleteContactRecords'));
    }

    public function test_setup_view_grants_access_setup_gate(): void
    {
        $user = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => false,
        ]);

        $this->actingAs($user);
        $this->assertTrue(Gate::allows('accessSetup'));
        $this->assertFalse(Gate::allows('manageTeamStructure'));
    }

    public function test_setup_update_grants_manage_team_structure(): void
    {
        $user = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => true,
        ]);

        $this->actingAs($user);
        $this->assertTrue(Gate::allows('manageTeamStructure'));
    }

    public function test_co_user_keeps_potential_institution_access(): void
    {
        $coUser = User::factory()->create(['team' => 'CO']);

        $this->actingAs($coUser);
        $this->assertTrue(Gate::allows('managePotentialInstitutions'));
    }
}
