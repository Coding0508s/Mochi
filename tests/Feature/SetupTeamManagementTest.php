<?php

namespace Tests\Feature;

use App\Livewire\SetupTeamManagement;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SetupTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('department')) {
            Schema::create('department', function (Blueprint $table): void {
                $table->string('DEPTNO')->primary();
                $table->string('DEPTNAME')->nullable();
                $table->string('MGRNO')->nullable();
                $table->string('ADMRDEPT')->nullable();
                $table->string('LOCATION')->nullable();
            });
        }

        if (! Schema::hasTable('employee')) {
            Schema::create('employee', function (Blueprint $table): void {
                $table->string('EMPNO')->primary();
                $table->string('WORKDEPT')->nullable();
            });
        }

        Department::query()->insert([
            [
                'DEPTNO' => 'A01',
                'DEPTNAME' => 'CEO',
                'MGRNO' => '',
                'ADMRDEPT' => '',
                'LOCATION' => '1',
            ],
            [
                'DEPTNO' => 'A05',
                'DEPTNAME' => 'Coach',
                'MGRNO' => '',
                'ADMRDEPT' => 'A01',
                'LOCATION' => '0',
            ],
        ]);
    }

    public function test_update_team_allows_unassigned_parent_department(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SetupTeamManagement::class)
            ->call('openEditModal', 'A05')
            ->set('editAdmrDept', '')
            ->set('editDeptName', 'Coach')
            ->call('updateTeam')
            ->assertHasNoErrors();

        $this->assertSame('', Department::query()->find('A05')?->ADMRDEPT);
    }

    public function test_update_team_clears_invalid_parent_department_on_open(): void
    {
        Department::query()->where('DEPTNO', 'A05')->update(['ADMRDEPT' => 'A99']);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SetupTeamManagement::class)
            ->call('openEditModal', 'A05')
            ->assertSet('editAdmrDept', '');
    }

    public function test_update_team_forgets_people_sidebar_cache(): void
    {
        Cache::put(Department::PEOPLE_SIDEBAR_CACHE_KEY, [
            ['DEPTNO' => 'A01', 'DEPTNAME' => 'CEO'],
        ], 600);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SetupTeamManagement::class)
            ->call('openEditModal', 'A05')
            ->set('editDeptName', 'Coach Team')
            ->call('updateTeam')
            ->assertHasNoErrors();

        $this->assertFalse(Cache::has(Department::PEOPLE_SIDEBAR_CACHE_KEY));
    }

    public function test_deputy_admin_cannot_update_team_and_sees_warning(): void
    {
        $deputy = User::factory()->deputyAdmin()->create();

        Livewire::actingAs($deputy)
            ->test(SetupTeamManagement::class)
            ->call('openEditModal', 'A05')
            ->assertSet('showEditModal', false)
            ->assertSee('관리자 또는 Setup 관리 권한이 필요합니다.');

        $this->assertSame('Coach', Department::query()->find('A05')?->DEPTNAME);
    }

    public function test_deputy_admin_cannot_delete_team_and_sees_warning(): void
    {
        $deputy = User::factory()->deputyAdmin()->create();

        Livewire::actingAs($deputy)
            ->test(SetupTeamManagement::class)
            ->call('openDeleteModal', 'A05')
            ->assertSet('showDeleteModal', false)
            ->assertSee('관리자만 삭제할 수 있습니다.');

        $this->assertNotNull(Department::query()->find('A05'));
    }

    public function test_setup_manage_user_can_update_but_not_delete_team(): void
    {
        $manager = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => true,
        ]);

        Livewire::actingAs($manager)
            ->test(SetupTeamManagement::class)
            ->call('openEditModal', 'A05')
            ->assertSet('showEditModal', true)
            ->set('editDeptName', 'Coach Updated')
            ->call('updateTeam')
            ->assertHasNoErrors()
            ->assertSee('팀(A05) 정보가 수정되었습니다.');

        $this->assertSame('Coach Updated', Department::query()->find('A05')?->DEPTNAME);

        Livewire::actingAs($manager)
            ->test(SetupTeamManagement::class)
            ->call('openDeleteModal', 'A05')
            ->assertSet('showDeleteModal', false)
            ->assertSee('관리자만 삭제할 수 있습니다.');

        $this->assertNotNull(Department::query()->find('A05'));
    }
}
