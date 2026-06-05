<?php

namespace Tests\Feature;

use App\Livewire\SetupTeamManagement;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
