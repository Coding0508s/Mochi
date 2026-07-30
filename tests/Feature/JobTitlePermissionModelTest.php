<?php

namespace Tests\Feature;

use App\Models\JobTitlePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTitlePermissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_title_permission_persists_flags(): void
    {
        $row = JobTitlePermission::query()->create([
            'job_code' => 'Department Manager',
            'setup_view' => true,
            'setup_manage' => true,
            'can_manage_store_inventory' => false,
            'is_gs_brochure_admin' => false,
            'is_coach_team_lead' => true,
            'can_view_all_institutions' => false,
            'is_deputy_admin' => false,
        ]);

        $this->assertDatabaseHas('job_title_permissions', [
            'id' => $row->id,
            'job_code' => 'Department Manager',
            'setup_manage' => 1,
            'is_coach_team_lead' => 1,
        ]);
    }
}
