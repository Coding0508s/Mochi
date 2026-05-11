<?php

namespace Tests\Feature;

use App\Livewire\TeamScheduleCalendar;
use App\Models\TeamSchedule;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class TeamScheduleCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEmployeeTable();
    }

    public function test_team_user_filter_shows_only_users_linked_to_employee(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'team' => 'CO',
            'is_admin' => true,
            'is_active' => true,
        ]);

        $linkedUser = User::factory()->create([
            'name' => 'Linked User',
            'team' => 'CO',
            'employee_empno' => 'EMP-001',
            'is_active' => true,
        ]);

        $missingEmployeeUser = User::factory()->create([
            'name' => 'Missing Employee User',
            'team' => 'CO',
            'employee_empno' => 'EMP-MISSING',
            'is_active' => true,
        ]);

        $unlinkedUser = User::factory()->create([
            'name' => 'Unlinked User',
            'team' => 'CO',
            'employee_empno' => null,
            'is_active' => true,
        ]);

        $inactiveLinkedUser = User::factory()->create([
            'name' => 'Inactive Linked User',
            'team' => 'CO',
            'employee_empno' => 'EMP-002',
            'is_active' => false,
        ]);

        $this->insertEmployee('EMP-001', $linkedUser->email);
        $this->insertEmployee('EMP-002', $inactiveLinkedUser->email);

        Livewire::actingAs($admin)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSee('Linked User')
            ->assertDontSee('Missing Employee User')
            ->assertDontSee('Unlinked User')
            ->assertDontSee('Inactive Linked User');
    }

    public function test_team_view_shows_team_schedules_only_from_employee_linked_users(): void
    {
        $viewer = User::factory()->create([
            'name' => 'Viewer',
            'team' => 'CO',
            'employee_empno' => null,
            'is_active' => true,
        ]);

        $linkedOwner = User::factory()->create([
            'name' => 'Linked Owner',
            'team' => 'CO',
            'employee_empno' => 'EMP-101',
            'is_active' => true,
        ]);

        $unlinkedOwner = User::factory()->create([
            'name' => 'Unlinked Owner',
            'team' => 'CO',
            'employee_empno' => null,
            'is_active' => true,
        ]);

        $missingEmployeeOwner = User::factory()->create([
            'name' => 'Missing Employee Owner',
            'team' => 'CO',
            'employee_empno' => 'EMP-404',
            'is_active' => true,
        ]);

        $this->insertEmployee('EMP-101', $linkedOwner->email);

        TeamSchedule::query()->create([
            'user_id' => $viewer->id,
            'title' => '내 직원 미연결 일정',
            'starts_at' => now()->startOfMonth()->addDays(2),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'etc',
        ]);

        TeamSchedule::query()->create([
            'user_id' => $linkedOwner->id,
            'title' => '연결 직원 팀 일정',
            'starts_at' => now()->startOfMonth()->addDays(3),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'etc',
        ]);

        TeamSchedule::query()->create([
            'user_id' => $unlinkedOwner->id,
            'title' => '직원 미연결 팀 일정',
            'starts_at' => now()->startOfMonth()->addDays(4),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'etc',
        ]);

        TeamSchedule::query()->create([
            'user_id' => $missingEmployeeOwner->id,
            'title' => '직원 행 없는 팀 일정',
            'starts_at' => now()->startOfMonth()->addDays(5),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'etc',
        ]);

        Livewire::actingAs($viewer)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSee('내 직원 미연결 일정')
            ->assertSee('연결 직원 팀 일정')
            ->assertDontSee('직원 미연결 팀 일정')
            ->assertDontSee('직원 행 없는 팀 일정');
    }

    private function createEmployeeTable(): void
    {
        if (Schema::hasTable('employee')) {
            return;
        }

        Schema::create('employee', function (Blueprint $table): void {
            $table->string('EMPNO')->primary();
            $table->string('KOREANAME')->nullable();
            $table->string('ENGLISHNAME')->nullable();
            $table->string('EMAIL')->nullable();
            $table->string('WORKDEPT')->nullable();
            $table->integer('STATUS')->nullable();
        });
    }

    private function insertEmployee(string $empNo, string $email): void
    {
        DB::table('employee')->insert([
            'EMPNO' => $empNo,
            'KOREANAME' => $empNo,
            'ENGLISHNAME' => $empNo,
            'EMAIL' => $email,
            'WORKDEPT' => 'CO',
            'STATUS' => 1,
        ]);
    }
}
