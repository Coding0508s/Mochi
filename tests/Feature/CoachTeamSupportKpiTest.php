<?php

namespace Tests\Feature;

use App\Livewire\CoachTeacherSupportList;
use App\Livewire\CoachTeamSupportKpiDashboard;
use App\Models\User;
use Livewire\Livewire;

class CoachTeamSupportKpiTest extends CoachTeacherSupportListTest
{
    public function test_coach_without_team_lead_flag_cannot_access_team_kpi_page(): void
    {
        $coach = User::factory()->create([
            'name' => 'Coach A',
            'email' => 'coacha@example.com',
            'team' => 'TR',
            'is_coach_team_lead' => false,
        ]);

        $this->actingAs($coach)
            ->get(route('coach.team-kpi.index', ['team_menu' => 'coach']))
            ->assertForbidden();
    }

    public function test_coach_team_lead_sees_team_kpi_breakdown_by_tr(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create([
            'name' => 'Team Lead',
            'email' => 'lead@example.com',
        ]);

        $this->seedTeachersForKpi($year);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->assertOk()
            ->assertSee('Coach Team 지원 KPI')
            ->assertSee('Coach A')
            ->assertSee('Coach B')
            ->assertViewHas('teamKpis', fn (array $kpis): bool => $kpis['first_round'] === 2)
            ->assertViewHas('coachRows', function ($rows): bool {
                $coachA = $rows->firstWhere('coach', 'Coach A');

                return $coachA !== null
                    && $coachA['first_round'] === 1
                    && $coachA['teacher_count'] === 1;
            });
    }

    public function test_admin_can_access_team_kpi_page(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->get(route('coach.team-kpi.index'))
            ->assertOk();
    }

    public function test_coach_name_opens_schedule_modal_with_planned_rounds_only(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '계획교사', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);
        $this->createTeacher('SK001', '미계획교사', [
            '_1st_Support_Date' => "{$year}-03-10",
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->call('openCoachScheduleModal', 'Coach A')
            ->assertSet('showCoachScheduleModal', true)
            ->assertSee('계획교사')
            ->assertDontSee('미계획교사')
            ->assertSee('지원 완료 차수 집계')
            ->assertSee('계획 일정 집계')
            ->assertSee('1차')
            ->assertSee('계획일')
            ->assertViewHas('coachScheduleSummary', fn (array $summary): bool => $summary['teacher_count'] === 1
                && $summary['planned_round_count'] === 1
                && $summary['pending_count'] === 1)
            ->call('closeCoachScheduleModal')
            ->assertSet('showCoachScheduleModal', false);
    }

    public function test_team_kpi_row_links_to_teacher_support_with_coach_filter(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedTeachersForKpi($year);

        $component = Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', $year);

        $url = $component->instance()->teacherSupportUrl('Coach A');
        $this->assertStringContainsString('filterCoach=Coach', $url);
        $this->assertStringContainsString('filterYear='.$year, $url);

        Livewire::actingAs($lead)
            ->withQueryParams([
                'filterYear' => (string) $year,
                'filterCoach' => 'Coach A',
            ])
            ->test(CoachTeacherSupportList::class)
            ->assertSet('filterCoach', 'Coach A')
            ->assertSee('김교사')
            ->assertDontSee('이교사');
    }

    public function test_team_kpi_includes_third_and_fourth_round_counts(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '3차교사', [
            '_3rd_Support_Date' => "{$year}-08-10",
            'Plan_3rd_Support_Date' => "{$year}-08-01",
        ]);
        $this->createTeacher('SK001', '4차교사', [
            '_4th_Support_Date' => "{$year}-10-10",
            'Plan_4th_Support_Date' => "{$year}-10-01",
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->assertViewHas('teamKpis', fn (array $kpis): bool => $kpis['third_round'] === 1
                && $kpis['fourth_round'] === 1);
    }

    private function seedTeachersForKpi(int $year): void
    {
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);
        $this->createTeacher('SK002', '이교사', [
            '_1st_Support_Date' => "{$year}-04-10",
            'Plan_1st_Support_Date' => "{$year}-04-01",
        ]);
    }
}
