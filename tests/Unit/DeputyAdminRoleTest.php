<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DeputyAdminRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_deputy_admin_has_platform_wide_view_but_cannot_delete(): void
    {
        $deputy = User::factory()->deputyAdmin()->create([
            'team' => 'COACH',
        ]);

        $this->assertTrue($deputy->isDeputyAdmin());
        $this->assertTrue($deputy->hasPlatformWideViewAccess());
        $this->assertFalse($deputy->hasFullAccess());
        $this->assertFalse($deputy->canDeletePlatformData());
    }

    public function test_full_access_admin_can_delete(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->hasFullAccess());
        $this->assertTrue($admin->hasPlatformWideViewAccess());
        $this->assertTrue($admin->canDeletePlatformData());
        $this->assertFalse($admin->isDeputyAdmin());
    }

    public function test_delete_gates_allow_only_full_access_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $deputy = User::factory()->deputyAdmin()->create();
        $regular = User::factory()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('deleteContactRecords'));
        $this->assertTrue(Gate::allows('deleteSupportRecords'));
        $this->assertTrue(Gate::allows('deleteTeamStructure'));

        $this->actingAs($deputy);
        $this->assertFalse(Gate::allows('deleteContactRecords'));
        $this->assertFalse(Gate::allows('deleteSupportRecords'));
        $this->assertFalse(Gate::allows('deleteTeamStructure'));

        $this->actingAs($regular);
        $this->assertFalse(Gate::allows('deleteContactRecords'));
    }

    public function test_deputy_admin_inherits_coach_team_kpi_view_without_team_lead_flag(): void
    {
        $deputy = User::factory()->deputyAdmin()->create([
            'is_coach_team_lead' => false,
        ]);

        $this->assertTrue($deputy->canViewCoachTeamKpi());
    }
}
