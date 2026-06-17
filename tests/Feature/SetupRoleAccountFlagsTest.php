<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SetupRoleAccountFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_inventory_flag_grants_gate_without_admin(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'can_manage_store_inventory' => true,
        ]);

        $this->actingAs($user);
        $this->assertTrue(Gate::allows('manageStoreInventory'));
    }

    public function test_brochure_admin_flag_grants_gate_without_admin(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_gs_brochure_admin' => true,
        ]);

        $this->actingAs($user);
        $this->assertTrue(Gate::allows('manageGsBrochureAdmin'));
    }

    public function test_setup_permission_columns_are_cast_to_booleans(): void
    {
        $user = User::factory()->create([
            'setup_view' => 1,
            'setup_manage' => 0,
        ]);

        $this->assertTrue($user->setup_view);
        $this->assertFalse($user->setup_manage);
    }
}
