<?php

namespace Tests\Feature;

use App\Livewire\SetupJobTitlePermissionMatrix;
use App\Models\Employee;
use App\Models\SetupCommonCode;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SetupJobTitlePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('employee')) {
            Schema::create('employee', function (Blueprint $table): void {
                $table->string('EMPNO')->primary();
                $table->string('WORKDEPT')->nullable();
                $table->string('KOREANAME')->nullable();
                $table->string('ENGLISHNAME')->nullable();
                $table->string('JOB')->nullable();
                $table->string('EMAIL')->nullable();
                $table->string('PHONENO')->nullable();
                $table->integer('STATUS')->nullable();
                $table->date('HIREDATE')->nullable();
            });
        }
    }

    public function test_setup_manage_user_can_save_matrix_and_sync_users(): void
    {
        SetupCommonCode::query()->create([
            'category' => 'job_title',
            'code' => 'Coach',
            'label' => '코치',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Employee::query()->create(['EMPNO' => 'E1', 'JOB' => 'Coach', 'STATUS' => 1]);
        $target = User::factory()->create([
            'employee_empno' => 'E1',
            'is_admin' => false,
            'is_coach_team_lead' => false,
        ]);

        $actor = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => true,
        ]);

        Livewire::actingAs($actor)
            ->test(SetupJobTitlePermissionMatrix::class)
            ->set('rows.Coach.is_coach_team_lead', true)
            ->set('rows.Coach.setup_view', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('job_title_permissions', [
            'job_code' => 'Coach',
            'is_coach_team_lead' => 1,
        ]);
        $this->assertTrue((bool) $target->fresh()->is_coach_team_lead);
    }

    public function test_setup_view_only_cannot_save(): void
    {
        SetupCommonCode::query()->create([
            'category' => 'job_title',
            'code' => 'Staff',
            'label' => 'Staff',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $viewer = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => false,
        ]);

        Livewire::actingAs($viewer)
            ->test(SetupJobTitlePermissionMatrix::class)
            ->set('rows.Staff.setup_view', true)
            ->call('save')
            ->assertForbidden();
    }

    public function test_route_ok_for_setup_viewer(): void
    {
        $viewer = User::factory()->create(['setup_view' => true]);

        $this->actingAs($viewer)
            ->get(route('setup.job-title-permissions'))
            ->assertOk();
    }
}
