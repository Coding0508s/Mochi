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
        ], forLatestView: false);
        $this->createTeacher('SK001', '미계획교사', [
            '_1st_Support_Date' => "{$year}-03-10",
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->call('openCoachScheduleModal', 'Coach A')
            ->assertSet('showCoachScheduleModal', true)
            ->assertSee('계획교사')
            ->assertSee('미계획교사')
            ->assertSee($year.'년 3월 1일')
            ->assertSee($year.'년 3월 10일')
            ->assertSee('지원 완료 차수 집계')
            ->assertSee('계획 일정 집계')
            ->assertSee('1차')
            ->assertSee('계획일')
            ->assertViewHas('coachScheduleSummary', fn (array $summary): bool => $summary['teacher_count'] === 2
                && $summary['planned_round_count'] === 2
                && $summary['completed_count'] === 1
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

    public function test_by_coach_does_not_double_count_teacher_when_sk_has_multiple_tr_rows(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        \DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-SPLIT-TR',
            'AccountName' => '다중 TR 기관',
        ]);
        \DB::table('S_Account_Information')->insert([
            ['SK_Code' => 'SK-SPLIT-TR', 'Account_Name' => '다중 TR A', 'TR' => 'Coach A'],
            ['SK_Code' => 'SK-SPLIT-TR', 'Account_Name' => '다중 TR B', 'TR' => 'Coach B'],
        ]);
        $this->createTeacher('SK-SPLIT-TR', '단일교사', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ], forLatestView: false);

        $component = Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', $year);

        $teamKpis = $component->viewData('teamKpis');
        $coachRows = $component->viewData('coachRows');

        $this->assertSame(1, $teamKpis['unsupported']);
        $this->assertSame(1, $coachRows->sum('unsupported'));
        $this->assertSame(1, $coachRows->sum('teacher_count'));
    }

    public function test_team_kpis_match_sum_of_coach_row_metrics(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedTeachersForKpi($year);

        $component = Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', $year);

        $teamKpis = $component->viewData('teamKpis');
        $coachRows = $component->viewData('coachRows');

        foreach (['first_round', 'second_round', 'third_round', 'fourth_round', 'completed', 'any_completed', 'unsupported'] as $key) {
            $this->assertSame($teamKpis[$key], $coachRows->sum($key), "팀 합계와 Coach 행 합이 {$key}에서 일치해야 합니다.");
        }
    }

    public function test_any_completed_counts_teachers_with_at_least_one_completed_round(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        // 1차만 완료 → any_completed 대상, 전차 완료(completed)는 아님
        $this->createTeacher('SK001', '1차완료교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);
        // 계획만 있고 완료 없음 → any_completed 대상 아님(미지원)
        $this->createTeacher('SK001', '계획만교사', [
            'Plan_1st_Support_Date' => "{$year}-04-01",
        ], forLatestView: false);

        $component = Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', $year);

        $teamKpis = $component->viewData('teamKpis');

        $this->assertSame(1, $teamKpis['any_completed']);
        $this->assertSame(0, $teamKpis['completed']);
        $this->assertSame(1, $teamKpis['unsupported']);
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

    public function test_schedule_modal_filters_rows_and_completion_by_selected_year(): void
    {
        $year = now()->year;
        $previousYear = $year - 1;
        $lead = User::factory()->coachTeamLead()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '혼합교사', [
            'Plan_1st_Support_Date' => "{$previousYear}-08-01",
            '_1st_Support_Date' => "{$previousYear}-09-10",
            'Plan_2nd_Support_Date' => "{$year}-03-01",
            '_2nd_Support_Date' => "{$year}-03-15",
        ]);
        $this->createTeacher('SK001', '작년교사', [
            'Plan_1st_Support_Date' => "{$previousYear}-05-01",
            '_1st_Support_Date' => "{$year}-02-01",
        ]);
        $this->createTeacher('SK001', '연도교차교사', [
            'Plan_3rd_Support_Date' => "{$year}-08-01",
            '_3rd_Support_Date' => "{$previousYear}-12-20",
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', $year)
            ->call('openCoachScheduleModal', 'Coach A')
            ->assertSet('showCoachScheduleModal', true)
            ->assertViewHas('coachScheduleRows', function (array $rows) use ($year): bool {
                if (count($rows) !== 3) {
                    return false;
                }

                $mixed = collect($rows)->firstWhere('teacher_name', '혼합교사');
                $lastYearPlan = collect($rows)->firstWhere('teacher_name', '작년교사');
                $crossYear = collect($rows)->firstWhere('teacher_name', '연도교차교사');

                if ($mixed === null || $lastYearPlan === null || $crossYear === null) {
                    return false;
                }

                $mixedOk = ($mixed['rounds'][0]['label'] ?? '') === '2차'
                    && ($mixed['rounds'][0]['completed_date'] ?? '') === $year.'년 3월 15일';

                $lastYearPlanOk = ($lastYearPlan['rounds'][0]['label'] ?? '') === '1차'
                    && ($lastYearPlan['rounds'][0]['plan_date'] ?? '') === '—'
                    && ($lastYearPlan['rounds'][0]['completed_date'] ?? '') === $year.'년 2월 1일';

                $crossYearOk = ($crossYear['rounds'][0]['label'] ?? '') === '3차'
                    && ($crossYear['rounds'][0]['completed_date'] ?? '') === '—';

                return $mixedOk && $lastYearPlanOk && $crossYearOk;
            })
            ->assertViewHas('coachScheduleSummary', fn (array $summary): bool => $summary['teacher_count'] === 3
                && $summary['planned_round_count'] === 3
                && $summary['completed_count'] === 2
                && $summary['pending_count'] === 1);
    }

    public function test_schedule_modal_shows_round_with_completion_only_in_selected_year(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '완료만교사', [
            '_3rd_Support_Date' => "{$year}-08-20",
            '_4th_Support_Date' => "{$year}-10-15",
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', $year)
            ->call('openCoachScheduleModal', 'Coach A')
            ->assertSet('showCoachScheduleModal', true)
            ->assertSee('완료만교사')
            ->assertViewHas('coachScheduleRows', function (array $rows) use ($year): bool {
                $teacher = collect($rows)->firstWhere('teacher_name', '완료만교사');

                if ($teacher === null || count($teacher['rounds'] ?? []) !== 2) {
                    return false;
                }

                $third = collect($teacher['rounds'])->firstWhere('label', '3차');
                $fourth = collect($teacher['rounds'])->firstWhere('label', '4차');

                return $third !== null
                    && ($third['plan_date'] ?? '') === '—'
                    && ($third['completed_date'] ?? '') === $year.'년 8월 20일'
                    && $fourth !== null
                    && ($fourth['plan_date'] ?? '') === '—'
                    && ($fourth['completed_date'] ?? '') === $year.'년 10월 15일';
            })
            ->assertViewHas('coachScheduleSummary', fn (array $summary): bool => $summary['teacher_count'] === 1
                && $summary['planned_round_count'] === 2
                && $summary['completed_count'] === 2
                && $summary['pending_count'] === 0);
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
