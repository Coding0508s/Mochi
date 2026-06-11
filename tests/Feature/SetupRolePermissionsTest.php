<?php

namespace Tests\Feature;

use App\Models\SetupRole;
use App\Models\User;
use App\Support\SetupRolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SetupRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_edit_remains_admin_only(): void
    {
        $role = SetupRole::query()->create([
            'role_key' => 'legacy_people',
            'role_name' => '레거시 People',
            'description' => '',
            'is_active' => true,
            'permissions' => [
                'people' => ['view' => true, 'create' => false, 'update' => true, 'delete' => false],
            ],
            'account_flags' => [],
        ]);

        $user = User::factory()->create(['setup_role_id' => $role->id]);

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('editEmployeeProfile'));
    }

    public function test_delete_gates_remain_admin_only(): void
    {
        $role = SetupRole::query()->create([
            'role_key' => 'legacy_delete',
            'role_name' => '레거시 삭제',
            'description' => '',
            'is_active' => true,
            'permissions' => [
                'contacts' => ['view' => true, 'create' => false, 'update' => false, 'delete' => true],
            ],
            'account_flags' => [],
        ]);

        $user = User::factory()->create(['setup_role_id' => $role->id]);

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
        $role = SetupRole::query()->create([
            'role_key' => 'setup_viewer',
            'role_name' => 'Setup 조회',
            'description' => '',
            'is_active' => true,
            'permissions' => [
                'setup' => ['view' => true, 'create' => false, 'update' => false, 'delete' => false],
            ],
            'account_flags' => [],
        ]);

        $user = User::factory()->create(['setup_role_id' => $role->id]);

        $this->actingAs($user);
        $this->assertTrue(Gate::allows('accessSetup'));
        $this->assertFalse(Gate::allows('manageTeamStructure'));
    }

    public function test_setup_update_grants_manage_team_structure(): void
    {
        $role = SetupRole::query()->create([
            'role_key' => 'setup_delegate',
            'role_name' => 'Setup 위임',
            'description' => '',
            'is_active' => true,
            'permissions' => [
                'setup' => ['view' => true, 'create' => false, 'update' => true, 'delete' => false],
            ],
            'account_flags' => [],
        ]);

        $user = User::factory()->create(['setup_role_id' => $role->id]);

        $this->actingAs($user);
        $this->assertTrue(Gate::allows('manageTeamStructure'));
    }

    public function test_co_user_keeps_potential_institution_access(): void
    {
        $coUser = User::factory()->create(['team' => 'CO', 'setup_role_id' => null]);

        $this->actingAs($coUser);
        $this->assertTrue(Gate::allows('managePotentialInstitutions'));
    }

    public function test_normalize_matrix_drops_legacy_menu_keys(): void
    {
        $normalized = SetupRolePermissions::normalizeMatrix([
            'people' => ['view' => true, 'create' => true, 'update' => true, 'delete' => true],
            'setup' => ['view' => true, 'create' => false, 'update' => false, 'delete' => false],
        ]);

        $this->assertArrayHasKey('setup', $normalized);
        $this->assertArrayNotHasKey('people', $normalized);
        $this->assertTrue($normalized['setup']['view']);
    }
}
