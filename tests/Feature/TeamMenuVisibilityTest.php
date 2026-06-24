<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Support\TeamMenuContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_user_can_access_potential_institutions_route(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach)
            ->get('/potential-institutions')
            ->assertOk();
    }

    public function test_coach_user_can_access_store_inventory_route(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach)
            ->get('/store/inventory')
            ->assertOk();
    }

    public function test_co_user_can_access_potential_institutions_route(): void
    {
        $co = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $this->actingAs($co)
            ->get('/potential-institutions')
            ->assertOk();
    }

    public function test_cs_user_can_access_store_sales_route(): void
    {
        $cs = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $this->actingAs($cs)
            ->get('/store/sales')
            ->assertOk();
    }

    public function test_cs_team_sidebar_shows_brochure_request_menu_only(): void
    {
        $cs = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $response = $this->actingAs($cs)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('co/gs-brochure/request?team_menu=cs', false);
        $response->assertSee('sidebar-subitem-label">브로셔 신청<', false);
        $response->assertDontSee('sidebar-subitem-label">신청 내역<', false);
    }

    public function test_coach_team_sidebar_shows_brochure_request_menu_only(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $response = $this->actingAs($coach)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('co/gs-brochure/request?team_menu=coach', false);
        $response->assertSee('sidebar-subitem-label">브로셔 신청<', false);
        $response->assertDontSee('sidebar-subitem-label">신청 내역<', false);
    }

    public function test_cs_user_can_access_brochure_request_and_list_pages(): void
    {
        $cs = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $this->actingAs($cs)
            ->get(route('co.gs-brochure.request', ['team_menu' => 'cs']))
            ->assertOk();

        $this->actingAs($cs)
            ->get(route('co.gs-brochure.request', ['view' => 'list', 'team_menu' => 'cs']))
            ->assertOk();
    }

    public function test_admin_sidebar_shows_retired_teachers_under_admin_menu(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('>Admin<', false)
            ->assertSee('sidebar_context=admin', false)
            ->assertSee('sidebar-subitem-label">퇴직교사 리스트<', false);
    }

    public function test_admin_retired_teachers_page_highlights_admin_menu_only(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('coach.retired-teachers.index', [
                'team_menu' => 'coach',
                'sidebar_context' => 'admin',
            ]));

        $response->assertOk();

        $content = $response->getContent();
        $adminBlockStart = strpos($content, '>Admin<');
        $coachBlockStart = strpos($content, '>Coach Team<');

        $this->assertNotFalse($adminBlockStart);
        $this->assertNotFalse($coachBlockStart);

        $adminSection = substr($content, $adminBlockStart, $coachBlockStart - $adminBlockStart);
        $coachSection = substr($content, $coachBlockStart);

        $this->assertStringContainsString('sidebar_context=admin', $adminSection);
        $this->assertStringContainsString('sidebar-subitem-active', $adminSection);
        $this->assertStringNotContainsString('sidebar-subitem-active', $coachSection);
    }

    public function test_coach_retired_teachers_page_highlights_coach_team_menu_only(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('coach.retired-teachers.index', ['team_menu' => 'coach']));

        $response->assertOk();

        $content = $response->getContent();
        $adminBlockStart = strpos($content, '>Admin<');
        $coachBlockStart = strpos($content, '>Coach Team<');

        $adminSection = substr($content, $adminBlockStart, $coachBlockStart - $adminBlockStart);
        $coachSection = substr($content, $coachBlockStart);

        $this->assertStringNotContainsString('sidebar-subitem-active', $adminSection);
        $this->assertStringContainsString('sidebar-subitem-active', $coachSection);
    }

    public function test_non_admin_cannot_see_admin_menu(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('>Admin<', false);
    }

    public function test_administration_team_member_sees_admin_menu(): void
    {
        $this->ensureEmployeeTableExists();

        Employee::query()->create([
            'EMPNO' => 'ADM001',
            'WORKDEPT' => TeamMenuContext::DEPT_ADMINISTRATION,
            'KOREANAME' => 'Admin User',
            'EMAIL' => 'admin.user@example.com',
        ]);

        $administrationUser = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'ADM001',
        ]);

        $this->actingAs($administrationUser)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('>Admin<', false)
            ->assertSee('sidebar_context=admin', false)
            ->assertSee('sidebar-subitem-label">퇴직교사 리스트<', false);
    }

    public function test_administration_team_member_can_access_admin_retired_teachers_route(): void
    {
        $this->ensureEmployeeTableExists();

        Employee::query()->create([
            'EMPNO' => 'ADM002',
            'WORKDEPT' => TeamMenuContext::DEPT_ADMINISTRATION,
            'KOREANAME' => 'Admin User 2',
            'EMAIL' => 'admin.user2@example.com',
        ]);

        $administrationUser = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'ADM002',
        ]);

        $this->actingAs($administrationUser)
            ->get(route('coach.retired-teachers.index', [
                'sidebar_context' => 'admin',
            ]))
            ->assertOk()
            ->assertDontSee('타 팀 메뉴에서는 조회만 가능합니다', false);
    }

    public function test_administration_team_member_admin_retired_teachers_not_cross_team_read_only(): void
    {
        $this->ensureEmployeeTableExists();

        Employee::query()->create([
            'EMPNO' => 'ADM003',
            'WORKDEPT' => TeamMenuContext::DEPT_ADMINISTRATION,
            'KOREANAME' => 'Admin User 3',
            'EMAIL' => 'admin.user3@example.com',
        ]);

        $administrationUser = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'ADM003',
        ]);

        $this->actingAs($administrationUser)
            ->get(route('coach.retired-teachers.index', [
                'team_menu' => 'coach',
                'sidebar_context' => 'admin',
            ]))
            ->assertOk()
            ->assertDontSee('타 팀 메뉴에서는 조회만 가능합니다', false);

        $this->assertFalse(
            TeamMenuContext::isCrossTeamReadOnlyContext($administrationUser, TeamMenuContext::MENU_COACH)
        );
    }

    public function test_non_administration_user_cannot_access_admin_retired_teachers_route(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach)
            ->get(route('coach.retired-teachers.index', [
                'team_menu' => 'coach',
                'sidebar_context' => 'admin',
            ]))
            ->assertForbidden();
    }

    private function ensureEmployeeTableExists(): void
    {
        if (Schema::hasTable('employee')) {
            return;
        }

        Schema::create('employee', function (Blueprint $table): void {
            $table->string('EMPNO')->primary();
            $table->string('WORKDEPT')->nullable();
            $table->string('KOREANAME')->nullable();
            $table->string('ENGLISHNAME')->nullable();
            $table->string('EMAIL')->nullable();
        });
    }
}
