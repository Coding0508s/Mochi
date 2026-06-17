<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SetupRoleUserPermissionPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_setup_permission_flags_control_setup_gates(): void
    {
        $user = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => false,
        ]);

        $this->actingAs($user);
        $this->assertTrue(Gate::allows('accessSetup'));
        $this->assertFalse(Gate::allows('manageTeamStructure'));
    }

    public function test_admin_always_has_setup_manage_access(): void
    {
        $admin = User::factory()->admin()->create([
            'setup_view' => false,
            'setup_manage' => false,
        ]);

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('accessSetup'));
        $this->assertTrue(Gate::allows('manageTeamStructure'));
    }
}
