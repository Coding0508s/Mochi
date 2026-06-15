<?php

namespace Tests\Feature;

use App\Livewire\SetupRoleManagement;
use App\Models\SetupRole;
use App\Models\User;
use App\Support\SetupRoleAccountFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SetupRoleAccountFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_create_saves_account_flags(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SetupRoleManagement::class)
            ->call('openCreateModal')
            ->set('newRoleKey', 'gs_brochure_mgr')
            ->set('newRoleName', 'GS Brochure 관리자')
            ->set('newAccountFlags', [
                SetupRoleAccountFlags::FLAG_IS_ADMIN => false,
                SetupRoleAccountFlags::FLAG_IS_DEPUTY_ADMIN => false,
                SetupRoleAccountFlags::FLAG_IS_GS_BROCHURE_ADMIN => true,
                SetupRoleAccountFlags::FLAG_CAN_MANAGE_STORE_INVENTORY => false,
                SetupRoleAccountFlags::FLAG_IS_COACH_TEAM_LEAD => false,
            ])
            ->call('createRole')
            ->assertHasNoErrors();

        $role = SetupRole::query()->where('role_key', 'gs_brochure_mgr')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->normalizedAccountFlags()[SetupRoleAccountFlags::FLAG_IS_GS_BROCHURE_ADMIN]);
        $this->assertFalse($role->normalizedAccountFlags()[SetupRoleAccountFlags::FLAG_IS_ADMIN]);
    }

    public function test_assign_user_to_role_applies_flags_to_user(): void
    {
        $admin = User::factory()->admin()->create();

        $role = SetupRole::query()->create([
            'role_key' => 'store_manager',
            'role_name' => '스토어 재고 관리',
            'description' => '',
            'is_active' => true,
            'permissions' => [],
            'account_flags' => [
                SetupRoleAccountFlags::FLAG_IS_ADMIN => false,
                SetupRoleAccountFlags::FLAG_IS_DEPUTY_ADMIN => false,
                SetupRoleAccountFlags::FLAG_IS_GS_BROCHURE_ADMIN => false,
                SetupRoleAccountFlags::FLAG_CAN_MANAGE_STORE_INVENTORY => true,
                SetupRoleAccountFlags::FLAG_IS_COACH_TEAM_LEAD => false,
            ],
        ]);

        $targetUser = User::factory()->create([
            'is_admin' => false,
            'can_manage_store_inventory' => false,
            'is_gs_brochure_admin' => false,
            'is_coach_team_lead' => false,
            'is_deputy_admin' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(SetupRoleManagement::class)
            ->call('openAssignModal', $role->id)
            ->set('assignUserId', (string) $targetUser->id)
            ->call('assignUserToRole')
            ->assertHasNoErrors();

        $targetUser->refresh();
        $this->assertSame($role->id, $targetUser->setup_role_id);
        $this->assertTrue((bool) $targetUser->can_manage_store_inventory);
        $this->assertFalse((bool) $targetUser->is_admin);
    }

    public function test_remove_user_from_role_clears_flags(): void
    {
        $admin = User::factory()->admin()->create();

        $role = SetupRole::query()->create([
            'role_key' => 'brochure_admin',
            'role_name' => '브로셔 관리자',
            'description' => '',
            'is_active' => true,
            'permissions' => [],
            'account_flags' => [
                SetupRoleAccountFlags::FLAG_IS_ADMIN => false,
                SetupRoleAccountFlags::FLAG_IS_DEPUTY_ADMIN => false,
                SetupRoleAccountFlags::FLAG_IS_GS_BROCHURE_ADMIN => true,
                SetupRoleAccountFlags::FLAG_CAN_MANAGE_STORE_INVENTORY => false,
                SetupRoleAccountFlags::FLAG_IS_COACH_TEAM_LEAD => false,
            ],
        ]);

        $targetUser = User::factory()->create([
            'setup_role_id' => $role->id,
            'is_gs_brochure_admin' => true,
            'is_admin' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(SetupRoleManagement::class)
            ->call('openAssignModal', $role->id)
            ->call('removeUserFromRole', $targetUser->id)
            ->assertHasNoErrors();

        $targetUser->refresh();
        $this->assertNull($targetUser->setup_role_id);
        $this->assertFalse((bool) $targetUser->is_gs_brochure_admin);
        $this->assertFalse((bool) $targetUser->can_manage_store_inventory);
        $this->assertFalse((bool) $targetUser->is_coach_team_lead);
    }
}
