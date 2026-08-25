<?php

namespace Tests\Feature;

use App\Livewire\CoachTeacherSupportList;
use App\Livewire\CoachTeamSupportKpiDashboard;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function test_admin_can_access_team_kpi_page(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->get(route('coach.team-kpi.index'))
            ->assertOk();
    }

    public function test_team_lead_sees_type_month_matrix_by_coach(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create([
            'name' => 'Team Lead',
            'email' => 'lead@example.com',
        ]);

        $this->seedMatrixSupports($year);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->assertOk()
            ->assertSee('Coach Team 지원 KPI')
            ->assertSee('Coach A')
            ->assertSee('Coach B')
            ->assertSee('전화')
            ->assertSee('On-Site')
            ->assertSee('LVA+FR/FB')
            ->assertViewHas('coachRows', function ($rows) use ($year): bool {
                $coachA = $rows->firstWhere('coach', 'Coach A');
                $coachB = $rows->firstWhere('coach', 'Coach B');
                $mar = sprintf('%04d-03', $year);
                $apr = sprintf('%04d-04', $year);
                $may = sprintf('%04d-05', $year);

                return $coachA !== null
                    && $coachB !== null
                    && $coachA['total'] === 3
                    && $coachA['institution_total'] === 1
                    && $coachA['teacher_total'] === 2
                    && ($coachA['rows']['inst_phone'][$mar] ?? 0) === 1
                    && ($coachA['rows']['teacher_onsite'][$mar] ?? 0) === 1
                    && ($coachA['rows']['teacher_lva'][$apr] ?? 0) === 1
                    && $coachB['total'] === 1
                    && ($coachB['rows']['inst_visit'][$may] ?? 0) === 1;
            })
            ->assertViewHas('teamTotal', 4)
            ->assertViewHas('periodColumns', function ($columns) use ($year): bool {
                $keys = collect($columns)->pluck('key')->all();
                $spillover = collect($columns)->where('is_spillover', true)->pluck('label')->all();

                return in_array(sprintf('%04d-01', $year), $keys, true)
                    && in_array(sprintf('%04d-03', $year + 1), $keys, true)
                    && count($columns) === 15
                    && $spillover === [
                        (($year + 1) % 100).'년 1월',
                        (($year + 1) % 100).'년 2월',
                        (($year + 1) % 100).'년 3월',
                    ];
            })
            ->assertViewHas('activeCoachRows', fn ($rows): bool => $rows->pluck('coach')->values()->all() === ['Coach A', 'Coach B'])
            ->assertViewHas('zeroCoachRows', fn ($rows): bool => $rows->isEmpty());
    }

    public function test_cell_click_opens_list_modal_then_detail(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '김교사', [], forLatestView: false);

        DB::table('S_SupportInfo_Account')->insert([
            'Year' => $year,
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'TR_Name' => 'Coach A',
            'Support_Date' => "{$year}-03-10",
            'Support_Type' => '전화',
            'Status' => '완료',
            'Target' => '원장',
        ]);

        DB::table('teacher_onsite_support_reports')->insert([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '김교사',
            'support_date' => "{$year}-03-15",
            'status' => '완료',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->call('openCellModal', 'Coach A', 'inst_phone', sprintf('%04d-03', $year))
            ->assertSet('showListModal', true)
            ->assertSee('원장')
            ->assertSee('전화');

        $items = $component->get('listModalItems');
        $this->assertCount(1, $items);
        $this->assertSame('account:1', $items[0]['detail_key']);

        $component
            ->call('openDetailFromList', $items[0]['detail_key'])
            ->assertSet('showDetailModal', true)
            ->assertNotSet('selectedDetail', null)
            ->call('closeTeacherSupportHistoryDetailModal')
            ->assertSet('showDetailModal', false)
            ->call('closeListModal')
            ->assertSet('showListModal', false);
    }

    public function test_onsite_row_includes_ls_onsite_when_configured(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '김교사', [], forLatestView: false);

        DB::table('teacher_ls_onsite_lva_support_reports')->insert([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '김교사',
            'support_date' => "{$year}-06-01",
            'status' => '완료',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->assertViewHas('coachRows', function ($rows) use ($year): bool {
                $coachA = $rows->firstWhere('coach', 'Coach A');
                $jun = sprintf('%04d-06', $year);

                return $coachA !== null
                    && ($coachA['rows']['teacher_onsite'][$jun] ?? 0) === 1
                    && $coachA['total'] === 1;
            });
    }

    public function test_demo_lesson_counts_in_teacher_matrix_row(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '김교사', [], forLatestView: false);

        DB::table('teacher_demo_lesson_support_reports')->insert([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '김교사',
            'support_date' => "{$year}-04-10",
            'status' => '완료',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('S_Support_NewTeacher')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => "{$year}-04-20",
            'Teacher' => '김교사',
            'TR_Name' => 'Coach A',
            'Status' => '완료',
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', (string) $year)
            ->assertSee('신규교사 시연수업')
            ->assertViewHas('coachRows', function ($rows) use ($year): bool {
                $coachA = $rows->firstWhere('coach', 'Coach A');
                $apr = sprintf('%04d-04', $year);

                return $coachA !== null
                    && ($coachA['rows']['teacher_demo'][$apr] ?? 0) === 2
                    && $coachA['teacher_total'] === 2
                    && $coachA['total'] === 2;
            });
    }

    public function test_institution_excludes_incomplete_and_teacher_synced_types(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-01",
                'Support_Type' => '전화',
                'Status' => '진행중',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-02",
                'Support_Type' => 'On-Site',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-03",
                'Support_Type' => '대면',
                'Status' => '완료',
            ],
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->assertViewHas('coachRows', function ($rows) use ($year): bool {
                $coachA = $rows->firstWhere('coach', 'Coach A');
                $mar = sprintf('%04d-03', $year);

                return $coachA !== null
                    && $coachA['total'] === 1
                    && ($coachA['rows']['inst_visit'][$mar] ?? 0) === 1
                    && ($coachA['rows']['inst_phone'][$mar] ?? 0) === 0;
            });
    }

    public function test_search_filters_coach_rows(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedMatrixSupports($year);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('searchCoach', 'Coach B')
            ->assertSee('Coach B')
            ->assertDontSee('Coach A')
            ->assertViewHas('coachRows', fn ($rows): bool => $rows->count() === 1
                && $rows->first()['coach'] === 'Coach B');
    }

    public function test_team_kpi_row_links_to_teacher_support_with_coach_filter(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedMatrixSupports($year);

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
            ->assertSee('김교사');
    }

    private function seedCoachTeamEmployees(string ...$englishNames): void
    {
        foreach ($englishNames as $index => $name) {
            DB::table('employee')->insert([
                'EMPNO' => 'C'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'WORKDEPT' => 'A05',
                'KOREANAME' => $name.' 한글',
                'ENGLISHNAME' => $name,
                'EMAIL' => strtolower(str_replace(' ', '', $name)).'@example.com',
                'STATUS' => 1,
            ]);
        }
    }

    private function seedMatrixSupports(int $year): void
    {
        $this->seedCoachTeamEmployees('Coach A', 'Coach B');
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $teacherA = $this->createTeacher('SK001', '김교사', [], forLatestView: false);
        $this->createTeacher('SK002', '이교사', [], forLatestView: false);

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-10",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK002',
                'Account_Name' => '기관B',
                'TR_Name' => 'Coach B',
                'Support_Date' => "{$year}-05-12",
                'Support_Type' => '대면',
                'Status' => '완료',
            ],
        ]);

        DB::table('teacher_onsite_support_reports')->insert([
            'teacher_id' => $teacherA,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '김교사',
            'support_date' => "{$year}-03-20",
            'status' => '완료',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('teacher_lva_fb_support_reports')->insert([
            'teacher_id' => $teacherA,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '김교사',
            'support_date' => "{$year}-04-05",
            'status' => '완료',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_legacy_duplicate_of_mochi_is_not_double_counted(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '김교사', [], forLatestView: false);

        DB::table('teacher_onsite_support_reports')->insert([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '김교사',
            'support_date' => "{$year}-03-15",
            'status' => '완료',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('S_Support_OnSite')->insert([
            'TR_Name' => 'Coach A',
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'Teacher' => '김교사',
            'TeacherId' => $teacherId,
            'SupportDate' => "{$year}-03-15",
            'Status' => '완료',
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->assertViewHas('coachRows', function ($rows) use ($year): bool {
                $coachA = $rows->firstWhere('coach', 'Coach A');
                $mar = sprintf('%04d-03', $year);

                return $coachA !== null
                    && $coachA['total'] === 1
                    && ($coachA['rows']['teacher_onsite'][$mar] ?? 0) === 1;
            });
    }

    public function test_coach_name_variants_are_grouped_together(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-01",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach.A',
                'Support_Date' => "{$year}-04-01",
                'Support_Type' => '대면',
                'Status' => '완료',
            ],
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->assertViewHas('coachRows', function ($rows): bool {
                return $rows->count() === 1
                    && $rows->first()['total'] === 2;
            });
    }

    public function test_coach_rows_are_sorted_alphabetically(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Zoe Coach', 'Amy Coach', 'Zero Coach');
        $this->createInstitution('SK001', '기관A', 'Zoe Coach');
        $this->createInstitution('SK002', '기관B', 'Amy Coach');

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Zoe Coach',
                'Support_Date' => "{$year}-03-01",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Zoe Coach',
                'Support_Date' => "{$year}-03-02",
                'Support_Type' => '대면',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK002',
                'Account_Name' => '기관B',
                'TR_Name' => 'Amy Coach',
                'Support_Date' => "{$year}-03-03",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', (string) $year)
            ->assertSee('지원 없음 1명')
            ->assertViewHas('activeCoachRows', function ($rows): bool {
                return $rows->pluck('coach')->values()->all() === ['Amy Coach', 'Zoe Coach']
                    && $rows->first()['institution_total'] === 1
                    && $rows->first()['teacher_total'] === 0;
            })
            ->assertViewHas('zeroCoachRows', function ($rows): bool {
                return $rows->count() === 1
                    && $rows->first()['coach'] === 'Zero Coach'
                    && $rows->first()['total'] === 0;
            });
    }

    public function test_export_downloads_support_detail_excel(): void
    {
        $year = now()->year;
        $now = now();
        Carbon::setTestNow($now);
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedMatrixSupports($year);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->assertSee('엑셀 다운로드')
            ->call('exportToExcel')
            ->assertFileDownloaded('Coach_Team_지원내역_'.$year.'_'.$now->format('Ymd_His').'.xlsx');

        Carbon::setTestNow();
    }

    public function test_all_years_option_sums_recent_four_years(): void
    {
        $year = now()->year;
        $previousYear = $year - 1;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-01",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $previousYear,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$previousYear}-03-15",
                'Support_Type' => '대면',
                'Status' => '완료',
            ],
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', (string) $year)
            ->assertViewHas('teamTotal', 1)
            ->set('filterYear', '')
            ->assertSee('전체 (최근 4년)')
            ->assertViewHas('teamTotal', 2)
            ->assertViewHas('periodColumns', function ($columns): bool {
                return count($columns) === 12
                    && collect($columns)->every(fn (array $col): bool => $col['is_spillover'] === false)
                    && collect($columns)->pluck('key')->all() === range(1, 12);
            })
            ->assertViewHas('coachRows', function ($rows): bool {
                $coachA = $rows->firstWhere('coach', 'Coach A');

                return $coachA !== null
                    && $coachA['total'] === 2
                    && ($coachA['rows']['inst_phone'][3] ?? 0) === 1
                    && ($coachA['rows']['inst_visit'][3] ?? 0) === 1;
            });
    }

    public function test_business_year_includes_next_year_q1_in_totals_and_columns(): void
    {
        $year = now()->year;
        $nextYear = $year + 1;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-06-10",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $nextYear,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$nextYear}-02-15",
                'Support_Type' => '대면',
                'Status' => '완료',
            ],
            [
                'Year' => $nextYear,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$nextYear}-04-01",
                'Support_Type' => '화상',
                'Status' => '완료',
            ],
        ]);

        $febKey = sprintf('%04d-02', $nextYear);
        $spilloverLabel = ($nextYear % 100).'년 2월';

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', (string) $year)
            ->assertSee($spilloverLabel)
            ->assertViewHas('teamTotal', 2)
            ->assertViewHas('coachRows', function ($rows) use ($year, $febKey): bool {
                $coachA = $rows->firstWhere('coach', 'Coach A');
                $jun = sprintf('%04d-06', $year);

                return $coachA !== null
                    && $coachA['total'] === 2
                    && ($coachA['rows']['inst_phone'][$jun] ?? 0) === 1
                    && ($coachA['rows']['inst_visit'][$febKey] ?? 0) === 1
                    && ($coachA['rows']['inst_video'][sprintf('%04d-04', $year + 1)] ?? 0) === 0;
            })
            ->set('filterYear', (string) $nextYear)
            ->assertViewHas('teamTotal', 2)
            ->assertViewHas('coachRows', function ($rows) use ($febKey, $nextYear): bool {
                $coachA = $rows->firstWhere('coach', 'Coach A');
                $apr = sprintf('%04d-04', $nextYear);

                return $coachA !== null
                    && $coachA['total'] === 2
                    && ($coachA['rows']['inst_visit'][$febKey] ?? 0) === 1
                    && ($coachA['rows']['inst_video'][$apr] ?? 0) === 1;
            });
    }

    public function test_spillover_cell_opens_list_modal(): void
    {
        $year = now()->year;
        $nextYear = $year + 1;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            'Year' => $nextYear,
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'TR_Name' => 'Coach A',
            'Support_Date' => "{$nextYear}-02-15",
            'Support_Type' => '전화',
            'Status' => '완료',
            'Target' => '원장',
        ]);

        $periodKey = sprintf('%04d-02', $nextYear);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->set('filterYear', (string) $year)
            ->call('openCellModal', 'Coach A', 'inst_phone', $periodKey)
            ->assertSet('showListModal', true)
            ->assertSet('listModalPeriodKey', $periodKey)
            ->assertSee('원장')
            ->assertSee(($nextYear % 100).'년 2월');
    }

    public function test_export_shows_error_when_no_data(): void
    {
        $lead = User::factory()->coachTeamLead()->create();
        $this->seedCoachTeamEmployees('Coach A');

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->call('exportToExcel')
            ->assertNoFileDownloaded()
            ->assertSee('다운로드할 데이터가 없습니다.');
    }

    public function test_only_active_coach_team_employees_are_listed(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        DB::table('employee')->insert([
            [
                'EMPNO' => 'CS01',
                'WORKDEPT' => 'A02',
                'ENGLISHNAME' => 'Cs Person',
                'STATUS' => 1,
            ],
            [
                'EMPNO' => 'C999',
                'WORKDEPT' => 'A05',
                'ENGLISHNAME' => 'Inactive Coach',
                'STATUS' => 0,
            ],
        ]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SKCS', 'CS기관', 'Cs Person');

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-01",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SKCS',
                'Account_Name' => 'CS기관',
                'TR_Name' => 'Cs Person',
                'Support_Date' => "{$year}-03-02",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Inactive Coach',
                'Support_Date' => "{$year}-03-03",
                'Support_Type' => '대면',
                'Status' => '완료',
            ],
        ]);

        Livewire::actingAs($lead)
            ->test(CoachTeamSupportKpiDashboard::class)
            ->assertSee('Coach A')
            ->assertDontSee('Cs Person')
            ->assertDontSee('Inactive Coach')
            ->assertViewHas('coachRows', function ($rows): bool {
                return $rows->count() === 1
                    && $rows->first()['coach'] === 'Coach A'
                    && $rows->first()['total'] === 1;
            });
    }
}
