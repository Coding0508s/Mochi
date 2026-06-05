<?php

namespace Tests\Feature;

use App\Livewire\SharedSupplyManager;
use App\Livewire\TeamScheduleCalendar;
use App\Models\SharedSupplyItem;
use App\Models\TeamSchedule;
use App\Models\User;
use App\Support\SharedSupplyExcelImporter;
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

        // 사번 없는 관리자(WORKDEPT null)는 People-Employees 기준 부서를 알 수 없어 팀원 목록을 보지 않는다
        Livewire::actingAs($admin)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertDontSee('Linked User')
            ->assertDontSee('Missing Employee User')
            ->assertDontSee('Unlinked User')
            ->assertDontSee('Inactive Linked User');
    }

    public function test_admin_with_workdept_sees_only_same_dept_members(): void
    {
        $adminA = User::factory()->create([
            'name' => 'Admin A',
            'is_admin' => true,
            'is_active' => true,
            'employee_empno' => 'EMP-ADMIN-A',
        ]);

        $sameTeamUser = User::factory()->create([
            'name' => 'Same Team User',
            'is_active' => true,
            'employee_empno' => 'EMP-SAME',
        ]);

        $otherTeamUser = User::factory()->create([
            'name' => 'Other Team User',
            'is_active' => true,
            'employee_empno' => 'EMP-OTHER',
        ]);

        $this->insertEmployee('EMP-ADMIN-A', $adminA->email, 'DEPT-A');
        $this->insertEmployee('EMP-SAME', $sameTeamUser->email, 'DEPT-A');
        $this->insertEmployee('EMP-OTHER', $otherTeamUser->email, 'DEPT-B');

        // WORKDEPT가 있는 관리자는 자신의 부서 팀원만 본다
        Livewire::actingAs($adminA)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSee('Same Team User')
            ->assertDontSee('Other Team User');
    }

    public function test_team_view_shows_team_schedules_only_from_employee_linked_users(): void
    {
        $viewer = User::factory()->create([
            'name' => 'Viewer',
            'team' => 'CO',
            'employee_empno' => 'EMP-VIEWER-101',
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

        $this->insertEmployee('EMP-VIEWER-101', $viewer->email, 'DEPT-A');
        $this->insertEmployee('EMP-101', $linkedOwner->email, 'DEPT-A');

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

    public function test_team_view_without_workdept_keeps_own_schedules_visible_only(): void
    {
        $viewer = User::factory()->create([
            'name' => 'No Dept Viewer',
            'team' => 'CO',
            'employee_empno' => 'EMP-NO-DEPT',
            'is_active' => true,
        ]);

        $other = User::factory()->create([
            'name' => 'Other Dept Owner',
            'team' => 'CO',
            'employee_empno' => 'EMP-OTHER-DEPT',
            'is_active' => true,
        ]);

        $this->insertEmployee('EMP-NO-DEPT', $viewer->email, null);
        $this->insertEmployee('EMP-OTHER-DEPT', $other->email, 'DEPT-A');

        TeamSchedule::query()->create([
            'user_id' => $viewer->id,
            'title' => '내 부서 미지정 팀 일정',
            'starts_at' => now()->startOfMonth()->addDays(2),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'etc',
        ]);

        TeamSchedule::query()->create([
            'user_id' => $other->id,
            'title' => '다른 사람 팀 일정',
            'starts_at' => now()->startOfMonth()->addDays(3),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'etc',
        ]);

        Livewire::actingAs($viewer)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSee('내 부서 미지정 팀 일정')
            ->assertDontSee('다른 사람 팀 일정');
    }

    public function test_team_users_empty_when_viewer_has_no_workdept(): void
    {
        $viewer = User::factory()->create([
            'name' => 'No Dept Viewer',
            'team' => 'CO',
            'employee_empno' => 'EMP-NO-DEPT',
            'is_active' => true,
        ]);

        $otherNullDeptUser = User::factory()->create([
            'name' => 'Also No Dept User',
            'team' => 'CO',
            'employee_empno' => 'EMP-ALSO-NO-DEPT',
            'is_active' => true,
        ]);

        $this->insertEmployee('EMP-NO-DEPT', $viewer->email, null);
        $this->insertEmployee('EMP-ALSO-NO-DEPT', $otherNullDeptUser->email, null);

        Livewire::actingAs($viewer)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertDontSee('Also No Dept User');
    }

    public function test_day_modal_shows_all_schedules_for_date(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $date = now()->startOfMonth()->addDays(6);

        foreach (range(1, 5) as $index) {
            TeamSchedule::query()->create([
                'user_id' => $user->id,
                'title' => "하루 일정 {$index}",
                'starts_at' => $date->copy()->setTime(8 + $index, 0),
                'visibility' => 'private',
                'status' => 'planned',
                'type' => 'etc',
            ]);
        }

        Livewire::actingAs($user)
            ->test(TeamScheduleCalendar::class)
            ->call('openDayModal', $date->format('Y-m-d'))
            ->assertSet('showDayModal', true)
            ->assertSee('하루 일정 1')
            ->assertSee('하루 일정 5');
    }

    public function test_view_only_modal_for_other_user_schedule(): void
    {
        $viewer = User::factory()->create([
            'team' => 'CO',
            'employee_empno' => 'EMP-VIEWER',
            'is_active' => true,
        ]);
        $owner = User::factory()->create([
            'team' => 'CO',
            'employee_empno' => 'EMP-OWNER',
            'is_active' => true,
        ]);
        $this->insertEmployee('EMP-VIEWER', $viewer->email);
        $this->insertEmployee('EMP-OWNER', $owner->email);

        $schedule = TeamSchedule::query()->create([
            'user_id' => $owner->id,
            'title' => '팀원 공개 일정',
            'starts_at' => now()->startOfMonth()->addDays(2),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'meeting',
        ]);

        Livewire::actingAs($viewer)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->call('openEditModal', (int) $schedule->id)
            ->assertSet('viewOnly', true)
            ->assertSee('다른 사람 일정은 보기만 가능합니다.');
    }

    public function test_filter_by_type(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        TeamSchedule::query()->create([
            'user_id' => $user->id,
            'title' => '미팅 필터 대상',
            'starts_at' => now()->startOfMonth()->addDays(2),
            'visibility' => 'private',
            'status' => 'planned',
            'type' => 'meeting',
        ]);
        TeamSchedule::query()->create([
            'user_id' => $user->id,
            'title' => '업무 필터 제외',
            'starts_at' => now()->startOfMonth()->addDays(3),
            'visibility' => 'private',
            'status' => 'planned',
            'type' => 'task',
        ]);

        Livewire::actingAs($user)
            ->test(TeamScheduleCalendar::class)
            ->set('filterType', 'meeting')
            ->assertSee('미팅 필터 대상')
            ->assertDontSee('업무 필터 제외');
    }

    public function test_filter_by_status(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        TeamSchedule::query()->create([
            'user_id' => $user->id,
            'title' => '완료 필터 대상',
            'starts_at' => now()->startOfMonth()->addDays(2),
            'visibility' => 'private',
            'status' => 'done',
            'type' => 'task',
        ]);
        TeamSchedule::query()->create([
            'user_id' => $user->id,
            'title' => '예정 필터 제외',
            'starts_at' => now()->startOfMonth()->addDays(3),
            'visibility' => 'private',
            'status' => 'planned',
            'type' => 'task',
        ]);

        Livewire::actingAs($user)
            ->test(TeamScheduleCalendar::class)
            ->set('filterStatus', 'done')
            ->assertSee('완료 필터 대상')
            ->assertDontSee('예정 필터 제외');
    }

    public function test_recurring_weekly_creates_52_instances(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)
            ->test(TeamScheduleCalendar::class)
            ->call('openCreateModal', now()->startOfMonth()->format('Y-m-d'))
            ->set('title', '주간 반복 회의')
            ->set('startTime', '09:00')
            ->set('endTime', '10:00')
            ->set('type', 'meeting')
            ->set('visibility', 'team')
            ->set('status', 'planned')
            ->set('recurrenceRule', 'weekly')
            ->call('save')
            ->assertHasNoErrors();

        $parent = TeamSchedule::query()
            ->where('title', '주간 반복 회의')
            ->whereNull('recurrence_parent_id')
            ->firstOrFail();

        $this->assertDatabaseCount('team_schedules', 53);
        $this->assertSame(52, TeamSchedule::query()->where('recurrence_parent_id', $parent->id)->count());
    }

    public function test_delete_recurring_single(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        [$parent, $child] = $this->createRecurringPair($user);

        Livewire::actingAs($user)
            ->test(TeamScheduleCalendar::class)
            ->call('openEditModal', (int) $child->id)
            ->call('delete')
            ->assertSet('showRecurrenceDeleteModal', true)
            ->set('recurrenceDeleteScope', 'single')
            ->call('confirmRecurringDelete');

        $this->assertDatabaseHas('team_schedules', ['id' => $parent->id]);
        $this->assertDatabaseMissing('team_schedules', ['id' => $child->id]);
    }

    public function test_delete_recurring_all_following(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        [$parent, $child] = $this->createRecurringPair($user);

        Livewire::actingAs($user)
            ->test(TeamScheduleCalendar::class)
            ->call('openEditModal', (int) $parent->id)
            ->call('delete')
            ->assertSet('showRecurrenceDeleteModal', true)
            ->set('recurrenceDeleteScope', 'all_following')
            ->call('confirmRecurringDelete');

        $this->assertDatabaseMissing('team_schedules', ['id' => $parent->id]);
        $this->assertDatabaseMissing('team_schedules', ['id' => $child->id]);
    }

    public function test_team_view_marks_schedules_created_by_viewer_with_owned_class(): void
    {
        $viewer = User::factory()->create([
            'employee_empno' => 'EMP-VIEWER-OWNED',
            'is_active' => true,
        ]);

        $teammate = User::factory()->create([
            'employee_empno' => 'EMP-TEAMMATE-OWNED',
            'is_active' => true,
        ]);

        $this->insertEmployee('EMP-VIEWER-OWNED', $viewer->email, 'DEPT-OWNED');
        $this->insertEmployee('EMP-TEAMMATE-OWNED', $teammate->email, 'DEPT-OWNED');

        TeamSchedule::query()->create([
            'user_id' => $viewer->id,
            'created_by' => $viewer->id,
            'title' => '뷰어가 등록한 팀 일정',
            'starts_at' => now()->startOfMonth()->addDays(2),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'meeting',
        ]);

        TeamSchedule::query()->create([
            'user_id' => $teammate->id,
            'created_by' => $teammate->id,
            'title' => '동료가 등록한 팀 일정',
            'starts_at' => now()->startOfMonth()->addDays(3),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'task',
        ]);

        Livewire::actingAs($viewer)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSee('초록색 = 나와 관련된 팀 일정')
            ->assertSeeHtml('mochi-calendar-event--owned-by-me');
    }

    public function test_team_view_does_not_mark_teammate_schedules_with_owned_class(): void
    {
        $viewer = User::factory()->create([
            'employee_empno' => 'EMP-VIEWER-ONLY-TEAM',
            'is_active' => true,
        ]);

        $teammate = User::factory()->create([
            'employee_empno' => 'EMP-TEAMMATE-ONLY-TEAM',
            'is_active' => true,
        ]);

        $this->insertEmployee('EMP-VIEWER-ONLY-TEAM', $viewer->email, 'DEPT-ONLY-TEAM');
        $this->insertEmployee('EMP-TEAMMATE-ONLY-TEAM', $teammate->email, 'DEPT-ONLY-TEAM');

        TeamSchedule::query()->create([
            'user_id' => $teammate->id,
            'created_by' => $teammate->id,
            'title' => '동료만 등록한 팀 일정',
            'starts_at' => now()->startOfMonth()->addDays(4),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'etc',
        ]);

        Livewire::actingAs($viewer)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSee('동료만 등록한 팀 일정')
            ->assertDontSeeHtml('mochi-calendar-event--owned-by-me');
    }

    public function test_team_view_marks_excel_imported_schedules_for_row_owner_with_owned_class(): void
    {
        if (! Schema::hasColumn('team_schedules', 'source_type')) {
            $this->markTestSkipped('team_schedules.source_type column is required.');
        }

        $uploader = User::factory()->create([
            'name' => '엑셀업로더',
            'employee_empno' => 'EMP-EXCEL-UP',
            'is_admin' => true,
            'is_active' => true,
        ]);

        $rowOwner = User::factory()->create([
            'name' => '엑셀담당자',
            'employee_empno' => 'EMP-EXCEL-ROW',
            'is_active' => true,
        ]);

        $this->insertEmployee('EMP-EXCEL-UP', $uploader->email, 'DEPT-EXCEL');
        $this->insertEmployee('EMP-EXCEL-ROW', $rowOwner->email, 'DEPT-EXCEL');

        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();
        $rows = [
            ['회사명 그레이프시드코리아 주식회사 2026/06/02 ~ 2026/06/30'],
            ['일자', '시작시간', '종료시간', '물품명', '사용자명', '제목', '적요'],
            ['2026/06/02', '09:00', '16:00', $item->name, '엑셀담당자', '[차량배차] 신청 및 예약', '엑셀 등록 테스트'],
        ];

        app(SharedSupplyExcelImporter::class)->importRows($rows, $uploader->id);

        $this->assertDatabaseHas('team_schedules', [
            'title' => '[차량배차] 신청 및 예약',
            'user_id' => $rowOwner->id,
            'created_by' => $uploader->id,
            'source_type' => TeamSchedule::SOURCE_TYPE_SHARED_SUPPLY,
        ]);

        Livewire::actingAs($uploader)
            ->test(TeamScheduleCalendar::class)
            ->set('month', '2026-06')
            ->set('viewMode', 'team')
            ->assertSee('[차량배차] 신청 및 예약')
            ->assertDontSeeHtml('mochi-calendar-event--owned-by-me');

        Livewire::actingAs($rowOwner)
            ->test(TeamScheduleCalendar::class)
            ->set('month', '2026-06')
            ->set('viewMode', 'team')
            ->assertSee('[차량배차] 신청 및 예약')
            ->assertSeeHtml('mochi-calendar-event--owned-by-me');
    }

    public function test_team_view_marks_manual_shared_supply_schedule_for_user_id_owner(): void
    {
        if (! Schema::hasColumn('team_schedules', 'source_type')) {
            $this->markTestSkipped('team_schedules.source_type column is required.');
        }

        $owner = User::factory()->create([
            'employee_empno' => 'EMP-SS-OWNER',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'employee_empno' => 'EMP-SS-ADMIN',
            'is_active' => true,
            'is_admin' => true,
        ]);

        $this->insertEmployee('EMP-SS-OWNER', $owner->email, 'DEPT-SS');
        $this->insertEmployee('EMP-SS-ADMIN', $admin->email, 'DEPT-SS');

        TeamSchedule::query()->create([
            'user_id' => $owner->id,
            'created_by' => $admin->id,
            'title' => '공용품 담당 일정',
            'starts_at' => now()->startOfMonth()->addDays(6),
            'visibility' => 'team',
            'status' => 'planned',
            'type' => 'etc',
            'source_type' => TeamSchedule::SOURCE_TYPE_SHARED_SUPPLY,
            'source_id' => 1,
        ]);

        Livewire::actingAs($owner)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSee('공용품 담당 일정')
            ->assertSeeHtml('mochi-calendar-event--owned-by-me');

        Livewire::actingAs($admin)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSee('공용품 담당 일정')
            ->assertDontSeeHtml('mochi-calendar-event--owned-by-me');
    }

    public function test_team_view_marks_ui_created_shared_supply_for_owner(): void
    {
        if (! Schema::hasColumn('team_schedules', 'source_type')) {
            $this->markTestSkipped('team_schedules.source_type column is required.');
        }

        $user = User::factory()->create([
            'employee_empno' => 'EMP-UI-SS',
            'is_active' => true,
        ]);

        $this->insertEmployee('EMP-UI-SS', $user->email, 'DEPT-UI-SS');

        $itemId = (int) SharedSupplyItem::query()->where('code', '00013')->value('id');

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', now()->startOfMonth()->addDays(7)->format('Y-m-d'))
            ->set('startTime', '10:00')
            ->set('endTime', '11:00')
            ->set('title', '[회의실] UI 등록 테스트')
            ->set('sharedSupplyItemId', $itemId)
            ->set('purpose', '초록 테두리 테스트')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSee('[회의실] UI 등록 테스트')
            ->assertSeeHtml('mochi-calendar-event--owned-by-me');
    }

    public function test_mine_view_does_not_mark_owned_class(): void
    {
        $viewer = User::factory()->create([
            'employee_empno' => 'EMP-VIEWER-MINE',
            'is_active' => true,
        ]);

        $this->insertEmployee('EMP-VIEWER-MINE', $viewer->email, 'DEPT-MINE');

        TeamSchedule::query()->create([
            'user_id' => $viewer->id,
            'created_by' => $viewer->id,
            'title' => '내 일정 탭 일정',
            'starts_at' => now()->startOfMonth()->addDays(5),
            'visibility' => 'private',
            'status' => 'planned',
            'type' => 'personal',
        ]);

        Livewire::actingAs($viewer)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'mine')
            ->assertSee('내 일정 탭 일정')
            ->assertDontSeeHtml('mochi-calendar-event--owned-by-me')
            ->assertDontSee('초록 테두리 = 내가 등록한 일정');
    }

    public function test_team_view_done_schedule_keeps_owned_border_class(): void
    {
        $viewer = User::factory()->create([
            'employee_empno' => 'EMP-VIEWER-DONE',
            'is_active' => true,
        ]);

        $this->insertEmployee('EMP-VIEWER-DONE', $viewer->email, 'DEPT-DONE');

        TeamSchedule::query()->create([
            'user_id' => $viewer->id,
            'created_by' => $viewer->id,
            'title' => '완료된 내 팀 일정',
            'starts_at' => now()->startOfMonth()->addDays(6),
            'visibility' => 'team',
            'status' => 'done',
            'type' => 'meeting',
        ]);

        Livewire::actingAs($viewer)
            ->test(TeamScheduleCalendar::class)
            ->set('viewMode', 'team')
            ->assertSeeHtml('mochi-calendar-event--done')
            ->assertSeeHtml('mochi-calendar-event--owned-by-me');
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

    private function insertEmployee(string $empNo, string $email, ?string $workdept = 'CO'): void
    {
        DB::table('employee')->insert([
            'EMPNO' => $empNo,
            'KOREANAME' => $empNo,
            'ENGLISHNAME' => $empNo,
            'EMAIL' => $email,
            'WORKDEPT' => $workdept,
            'STATUS' => 1,
        ]);
    }

    /**
     * @return array{TeamSchedule, TeamSchedule}
     */
    private function createRecurringPair(User $user): array
    {
        $parent = TeamSchedule::query()->create([
            'user_id' => $user->id,
            'title' => '반복 삭제 테스트',
            'starts_at' => now()->startOfMonth()->setTime(9, 0),
            'ends_at' => now()->startOfMonth()->setTime(10, 0),
            'visibility' => 'private',
            'status' => 'planned',
            'type' => 'meeting',
            'recurrence_rule' => 'weekly',
        ]);

        $child = TeamSchedule::query()->create([
            'user_id' => $user->id,
            'title' => '반복 삭제 테스트',
            'starts_at' => now()->startOfMonth()->addWeek()->setTime(9, 0),
            'ends_at' => now()->startOfMonth()->addWeek()->setTime(10, 0),
            'visibility' => 'private',
            'status' => 'planned',
            'type' => 'meeting',
            'recurrence_rule' => 'weekly',
            'recurrence_parent_id' => $parent->id,
        ]);

        return [$parent, $child];
    }
}
