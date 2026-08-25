<?php

namespace Tests\Feature;

use App\Livewire\CoachInstitutionCoverageList;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class CoachInstitutionCoverageListTest extends CoachTeacherSupportListTest
{
    public function test_authenticated_user_can_open_institution_coverage_page(): void
    {
        $lead = User::factory()->coachTeamLead()->create();

        $this->actingAs($lead)
            ->get(route('coach.institution-coverage.index', ['team_menu' => 'coach']))
            ->assertOk()
            ->assertSeeLivewire(CoachInstitutionCoverageList::class);
    }

    public function test_coach_without_team_lead_flag_cannot_access_institution_coverage_page(): void
    {
        $coach = User::factory()->create([
            'name' => 'Coach A',
            'email' => 'coacha-coverage@example.com',
            'team' => 'TR',
            'is_coach_team_lead' => false,
        ]);

        $this->actingAs($coach)
            ->get(route('coach.institution-coverage.index', ['team_menu' => 'coach']))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_institution_coverage_page(): void
    {
        $this->get(route('coach.institution-coverage.index'))
            ->assertRedirect();
    }

    public function test_coverage_list_shows_supported_and_unsupported_institutions(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '지원된기관', 'Coach A');
        $this->createInstitution('SK002', '미지원기관', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '김교사', [], forLatestView: false);

        DB::table('S_SupportInfo_Account')->insert([
            'Year' => $year,
            'SK_Code' => 'SK001',
            'Account_Name' => '지원된기관',
            'TR_Name' => 'Coach A',
            'Support_Date' => "{$year}-03-10",
            'Support_Type' => '전화',
            'Status' => '완료',
        ]);

        DB::table('teacher_onsite_support_reports')->insert([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '지원된기관',
            'teacher_name' => '김교사',
            'support_date' => "{$year}-03-20",
            'status' => '완료',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($lead)
            ->test(CoachInstitutionCoverageList::class)
            ->set('filterYear', (string) $year)
            ->assertSee('기관 지원 현황')
            ->assertSee('SK코드')
            ->assertSee('지원된기관')
            ->assertSee('미지원기관')
            ->assertSee('SK001')
            ->assertDontSee('기관·교사 지원')
            ->assertDontSee("{$year}-03-10")
            ->assertViewHas('institutions', function ($institutions): bool {
                $supported = collect($institutions->items())->firstWhere('sk_code', 'SK001');

                return is_array($supported)
                    && $supported['phone_count'] === 1
                    && $supported['visit_count'] === 0
                    && $supported['video_count'] === 0
                    && $supported['teacher_count'] === 1;
            })
            ->assertViewHas('counts', function (array $counts): bool {
                return $counts['total'] === 2
                    && $counts['inst_supported'] === 1
                    && $counts['inst_unsupported'] === 1;
            })
            ->assertSee('기관지원됨')
            ->assertSee('기관미지원')
            ->assertDontSee('교사지원됨')
            ->assertDontSee('교사미지원')
            ->assertDontSee('완전미지원');
    }

    public function test_support_type_columns_show_counts_not_dates(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '다중지원기관', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '다중지원기관',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-10",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '다중지원기관',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-04-15",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '다중지원기관',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-05-01",
                'Support_Type' => '대면',
                'Status' => '완료',
            ],
        ]);

        Livewire::actingAs($lead)
            ->test(CoachInstitutionCoverageList::class)
            ->set('filterYear', (string) $year)
            ->assertSee('다중지원기관')
            ->assertDontSee("{$year}-03-10")
            ->assertDontSee("{$year}-04-15")
            ->assertViewHas('institutions', function ($institutions): bool {
                $row = collect($institutions->items())->firstWhere('sk_code', 'SK001');

                return is_array($row)
                    && $row['phone_count'] === 2
                    && $row['visit_count'] === 1
                    && $row['video_count'] === 0
                    && $row['institution_total_count'] === 3;
            });
    }

    public function test_clicking_support_count_opens_type_detail_modal(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '상세기관', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '상세기관',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-10",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '상세기관',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-06-20",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
        ]);

        Livewire::actingAs($lead)
            ->test(CoachInstitutionCoverageList::class)
            ->set('filterYear', (string) $year)
            ->call('openTypeDetail', 'SK001', 'phone')
            ->assertSet('showTypeDetailModal', true)
            ->assertSet('typeDetailTypeLabel', '전화')
            ->assertSet('typeDetailInstitution', '상세기관')
            ->assertSee('전화 지원 내역')
            ->assertSee("{$year}-03-10")
            ->assertSee("{$year}-06-20")
            ->assertCount('typeDetailRows', 2)
            ->call('closeTypeDetail')
            ->assertSet('showTypeDetailModal', false)
            ->assertCount('typeDetailRows', 0);
    }

    public function test_clicking_type_detail_row_opens_support_content_modal(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '상세내용기관', 'Coach A');

        $supportId = DB::table('S_SupportInfo_Account')->insertGetId([
            'Year' => $year,
            'SK_Code' => 'SK001',
            'Account_Name' => '상세내용기관',
            'TR_Name' => 'Coach A',
            'Support_Date' => "{$year}-03-10",
            'Support_Type' => '전화',
            'Status' => '완료',
            'Issue' => '테스트 이슈 내용',
            'Target' => '원장',
        ]);

        Livewire::actingAs($lead)
            ->test(CoachInstitutionCoverageList::class)
            ->set('filterYear', (string) $year)
            ->call('openTypeDetail', 'SK001', 'phone')
            ->assertSet('showTypeDetailModal', true)
            ->call('openTypeDetailRecord', 'account:'.$supportId)
            ->assertSet('showTeacherSupportHistoryDetailModal', true)
            ->assertSee('지원 내역')
            ->assertSee('테스트 이슈 내용')
            ->assertSee('원장')
            ->call('closeTeacherSupportHistoryDetailModal')
            ->assertSet('showTeacherSupportHistoryDetailModal', false);
    }

    public function test_zero_count_type_detail_does_not_open(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '미지원상세', 'Coach A');

        Livewire::actingAs($lead)
            ->test(CoachInstitutionCoverageList::class)
            ->set('filterYear', (string) $year)
            ->call('openTypeDetail', 'SK001', 'visit')
            ->assertSet('showTypeDetailModal', false);
    }

    public function test_counts_exclude_co_team_authored_supports(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->seedCoTeamEmployee('Co Person');
        $this->createInstitution('SK001', '혼합지원기관', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '혼합지원기관',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-10",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '혼합지원기관',
                'TR_Name' => 'Co Person',
                'Support_Date' => "{$year}-04-15",
                'Support_Type' => '전화',
                'Status' => '완료',
            ],
            [
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '혼합지원기관',
                'TR_Name' => 'Co Person',
                'Support_Date' => "{$year}-05-01",
                'Support_Type' => '대면',
                'Status' => '완료',
            ],
        ]);

        Livewire::actingAs($lead)
            ->test(CoachInstitutionCoverageList::class)
            ->set('filterYear', (string) $year)
            ->assertViewHas('institutions', function ($institutions): bool {
                $row = collect($institutions->items())->firstWhere('sk_code', 'SK001');

                return is_array($row)
                    && $row['phone_count'] === 1
                    && $row['visit_count'] === 0
                    && $row['video_count'] === 0;
            })
            ->call('openTypeDetail', 'SK001', 'phone')
            ->assertSet('showTypeDetailModal', true)
            ->assertCount('typeDetailRows', 1)
            ->assertSee('Coach A')
            ->assertDontSee('Co Person');
    }

    private function seedCoTeamEmployee(string $englishName): void
    {
        $exists = DB::table('employee')->where('ENGLISHNAME', $englishName)->exists();
        if ($exists) {
            DB::table('employee')
                ->where('ENGLISHNAME', $englishName)
                ->update([
                    'WORKDEPT' => 'A02',
                    'STATUS' => 1,
                ]);

            return;
        }

        DB::table('employee')->insert([
            'EMPNO' => 'CO'.uniqid(),
            'WORKDEPT' => 'A02',
            'KOREANAME' => $englishName.' 한글',
            'ENGLISHNAME' => $englishName,
            'EMAIL' => strtolower(str_replace(' ', '', $englishName)).'@co.example.com',
            'STATUS' => 1,
        ]);
    }

    public function test_inst_unsupported_filter_hides_supported_institutions(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '지원된기관', 'Coach A');
        $this->createInstitution('SK002', '미지원기관', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            'Year' => $year,
            'SK_Code' => 'SK001',
            'Account_Name' => '지원된기관',
            'TR_Name' => 'Coach A',
            'Support_Date' => "{$year}-06-01",
            'Support_Type' => '대면',
            'Status' => '완료',
        ]);

        Livewire::actingAs($lead)
            ->test(CoachInstitutionCoverageList::class)
            ->set('filterYear', (string) $year)
            ->call('setCoverageFilter', 'inst_unsupported')
            ->assertSet('coverageFilter', 'inst_unsupported')
            ->assertSee('미지원기관')
            ->assertDontSee('지원된기관');
    }

    public function test_regular_coach_cannot_open_institution_coverage_livewire(): void
    {
        $this->seedCoachTeamEmployees('Coach A', 'Coach B');

        $coach = User::factory()->create([
            'name' => 'Coach A',
            'email' => 'coacha@example.com',
            'team' => 'TR',
            'is_coach_team_lead' => false,
        ]);

        DB::table('employee')
            ->where('ENGLISHNAME', 'Coach A')
            ->update(['EMAIL' => 'coacha@example.com']);

        $this->createInstitution('SK001', '내기관', 'Coach A');
        $this->createInstitution('SK002', '다른기관', 'Coach B');

        Livewire::actingAs($coach)
            ->test(CoachInstitutionCoverageList::class)
            ->assertForbidden();
    }

    public function test_incomplete_institution_support_is_not_counted(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();

        $this->seedCoachTeamEmployees('Coach A');
        $this->createInstitution('SK001', '진행중기관', 'Coach A');

        DB::table('S_SupportInfo_Account')->insert([
            'Year' => $year,
            'SK_Code' => 'SK001',
            'Account_Name' => '진행중기관',
            'TR_Name' => 'Coach A',
            'Support_Date' => "{$year}-04-01",
            'Support_Type' => '전화',
            'Status' => '진행중',
        ]);

        Livewire::actingAs($lead)
            ->test(CoachInstitutionCoverageList::class)
            ->set('filterYear', (string) $year)
            ->assertSee('진행중기관')
            ->assertViewHas('counts', function (array $counts): bool {
                return $counts['total'] === 1
                    && $counts['inst_supported'] === 0
                    && $counts['inst_unsupported'] === 1;
            });
    }

    public function test_export_downloads_excel(): void
    {
        $year = now()->year;
        $lead = User::factory()->coachTeamLead()->create();
        $now = now()->startOfSecond();
        Carbon::setTestNow($now);

        try {
            $this->seedCoachTeamEmployees('Coach A');
            $this->createInstitution('SK001', '기관A', 'Coach A');

            DB::table('S_SupportInfo_Account')->insert([
                'Year' => $year,
                'SK_Code' => 'SK001',
                'Account_Name' => '기관A',
                'TR_Name' => 'Coach A',
                'Support_Date' => "{$year}-03-10",
                'Support_Type' => '전화',
                'Status' => '완료',
            ]);

            Livewire::actingAs($lead)
                ->test(CoachInstitutionCoverageList::class)
                ->set('filterYear', (string) $year)
                ->call('exportToExcel')
                ->assertFileDownloaded('Coach_Team_기관지원현황_'.$year.'_'.$now->format('Ymd_His').'.xlsx');
        } finally {
            Carbon::setTestNow();
        }
    }

    private function seedCoachTeamEmployees(string ...$englishNames): void
    {
        foreach ($englishNames as $index => $name) {
            $exists = DB::table('employee')->where('ENGLISHNAME', $name)->exists();
            if ($exists) {
                DB::table('employee')
                    ->where('ENGLISHNAME', $name)
                    ->update([
                        'WORKDEPT' => 'A05',
                        'STATUS' => 1,
                    ]);

                continue;
            }

            DB::table('employee')->insert([
                'EMPNO' => 'C'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT).uniqid(),
                'WORKDEPT' => 'A05',
                'KOREANAME' => $name.' 한글',
                'ENGLISHNAME' => $name,
                'EMAIL' => strtolower(str_replace(' ', '', $name)).'@example.com',
                'STATUS' => 1,
            ]);
        }
    }
}
