<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobTitlePermission;
use App\Models\User;
use App\Support\JobTitlePermissionSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobTitlePermissionSynchronizerTest extends TestCase
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

    public function test_sync_user_copies_matrix_flags_and_forces_setup_view(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E100',
            'JOB' => 'Team Lead',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        JobTitlePermission::query()->create([
            'job_code' => 'Team Lead',
            'setup_view' => false,
            'setup_manage' => true,
            'can_manage_store_inventory' => true,
            'is_gs_brochure_admin' => false,
            'is_coach_team_lead' => true,
            'can_view_all_institutions' => false,
            'is_deputy_admin' => false,
        ]);

        $user = User::factory()->create([
            'employee_empno' => 'E100',
            'is_admin' => false,
            'setup_view' => false,
            'setup_manage' => false,
            'can_manage_store_inventory' => false,
            'is_coach_team_lead' => false,
        ]);

        $synced = app(JobTitlePermissionSynchronizer::class)->syncUser($user);

        $this->assertTrue($synced);
        $user->refresh();
        $this->assertTrue((bool) $user->setup_manage);
        $this->assertTrue((bool) $user->setup_view); // forced by setup_manage
        $this->assertTrue((bool) $user->can_manage_store_inventory);
        $this->assertTrue((bool) $user->is_coach_team_lead);
        $this->assertFalse((bool) $user->is_admin);
    }

    public function test_sync_skips_admin_user(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E200',
            'JOB' => 'Team Lead',
            'STATUS' => 1,
        ]);

        JobTitlePermission::query()->create([
            'job_code' => 'Team Lead',
            'setup_manage' => true,
            'setup_view' => true,
            'is_coach_team_lead' => true,
        ]);

        $admin = User::factory()->admin()->create([
            'employee_empno' => 'E200',
            'setup_view' => true,
            'setup_manage' => true,
            'is_coach_team_lead' => false,
        ]);
        $admin->refresh();

        $before = $admin->only(JobTitlePermissionSynchronizer::FLAG_COLUMNS);

        $synced = app(JobTitlePermissionSynchronizer::class)->syncUser($admin);

        $this->assertFalse($synced);
        $admin->refresh();
        $this->assertSame($before, $admin->only(JobTitlePermissionSynchronizer::FLAG_COLUMNS));
    }

    public function test_missing_matrix_row_clears_all_seven_flags(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E300',
            'JOB' => 'Legacy Title',
            'STATUS' => 1,
        ]);

        $user = User::factory()->create([
            'employee_empno' => 'E300',
            'is_admin' => false,
            'setup_view' => true,
            'setup_manage' => true,
            'can_manage_store_inventory' => true,
            'is_gs_brochure_admin' => true,
            'is_coach_team_lead' => true,
            'can_view_all_institutions' => true,
            'is_deputy_admin' => true,
        ]);

        app(JobTitlePermissionSynchronizer::class)->syncUser($user);

        $user->refresh();
        foreach (JobTitlePermissionSynchronizer::FLAG_COLUMNS as $column) {
            $this->assertFalse((bool) $user->{$column}, $column);
        }
    }

    public function test_sync_users_for_job_code_updates_matching_non_admins_only(): void
    {
        Employee::query()->create(['EMPNO' => 'E401', 'JOB' => 'Coach', 'STATUS' => 1]);
        Employee::query()->create(['EMPNO' => 'E402', 'JOB' => 'Coach', 'STATUS' => 1]);
        Employee::query()->create(['EMPNO' => 'E403', 'JOB' => 'Staff', 'STATUS' => 1]);

        JobTitlePermission::query()->create([
            'job_code' => 'Coach',
            'is_coach_team_lead' => true,
            'setup_view' => true,
        ]);

        $u1 = User::factory()->create(['employee_empno' => 'E401', 'is_admin' => false, 'is_coach_team_lead' => false]);
        $u2 = User::factory()->admin()->create(['employee_empno' => 'E402', 'is_coach_team_lead' => false]);
        $u3 = User::factory()->create(['employee_empno' => 'E403', 'is_admin' => false, 'is_coach_team_lead' => false]);

        $count = app(JobTitlePermissionSynchronizer::class)->syncUsersForJobCode('Coach');

        $this->assertSame(1, $count);
        $this->assertTrue((bool) $u1->fresh()->is_coach_team_lead);
        $this->assertFalse((bool) $u2->fresh()->is_coach_team_lead);
        $this->assertFalse((bool) $u3->fresh()->is_coach_team_lead);
    }
}
