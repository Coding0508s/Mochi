<?php

namespace Tests\Feature;

use App\Livewire\InstitutionList;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use App\Support\SupportAuthorTeamResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionDetailTeamSupportHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('features.institution_create_enabled', true);
        Config::set('services.institution_outbound.enabled', false);
        Queue::fake();
        $this->createAccountTables();
    }

    private function createAccountTables(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_GSNumber');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');
        Schema::dropIfExists('employee');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->timestamp('FGC_CreateDate')->nullable();
            $table->string('EnglishName', 255)->nullable();
            $table->string('PortalAccountName', 255)->nullable();
            $table->string('PortalCampusID', 100)->nullable();
            $table->string('AccountNo', 100)->nullable();
            $table->string('GSno', 100)->nullable();
            $table->string('Director', 255)->nullable();
            $table->string('Phone', 100)->nullable();
            $table->string('AccountTel', 100)->nullable();
            $table->string('Address', 255)->nullable();
            $table->string('Gubun', 100)->nullable();
            $table->string('Possibility', 20)->nullable();
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
            $table->string('Affiliate', 255)->nullable();
            $table->string('Address', 255)->nullable();
            $table->timestamp('FGC_CreateDate')->nullable();
        });

        Schema::create('S_GSNumber', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKCode', 100)->unique();
            $table->string('AccountName', 255)->nullable();
            $table->string('GSnumber', 100)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
        });

        Schema::create('Teachers', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 255)->nullable();
        });

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->string('Meet_Time', 50)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->string('Target', 255)->nullable();
            $table->text('Issue')->nullable();
            $table->text('TO_Account')->nullable();
            $table->text('TO_Depart')->nullable();
            $table->text('Others')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
        });

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

        Schema::dropIfExists('S_Support_NewTeacher');
        Schema::create('S_Support_NewTeacher', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
            $table->unsignedTinyInteger('ReportType')->nullable();
        });
    }

    public function test_detail_modal_groups_institution_support_by_team(): void
    {
        Employee::query()->create([
            'EMPNO' => 'CO01',
            'KOREANAME' => 'CO담당',
            'WORKDEPT' => 'A02',
        ]);
        Employee::query()->create([
            'EMPNO' => 'CS01',
            'KOREANAME' => 'CS담당',
            'WORKDEPT' => 'A03',
        ]);
        Employee::query()->create([
            'EMPNO' => 'TR01',
            'KOREANAME' => 'Coach담당',
            'WORKDEPT' => 'A05',
        ]);

        $institution = Institution::query()->create([
            'SKcode' => 'SK-TEAM-HIST',
            'AccountName' => '팀별 이력 기관',
        ]);

        SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-TEAM-HIST',
            'TR_Name' => 'CO담당',
            'Support_Date' => '2026-06-01',
            'Support_Type' => '방문',
            'Status' => '완료',
        ]);
        SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-TEAM-HIST',
            'TR_Name' => 'CS담당',
            'Support_Date' => '2026-06-02',
            'Support_Type' => '전화',
            'Status' => '진행중',
        ]);
        SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-TEAM-HIST',
            'TR_Name' => 'Coach담당',
            'Support_Date' => '2026-06-03',
            'Support_Type' => '온라인',
            'Status' => '완료',
        ]);
        SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-TEAM-HIST',
            'TR_Name' => '미분류작성자',
            'Support_Date' => '2026-06-04',
            'Support_Type' => '방문',
            'Status' => '진행중',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-TEAM-HIST',
            'Account_Name' => '팀별 이력 기관',
            'CO' => 'CO User',
        ]);

        Livewire::actingAs(User::factory()->create(['name' => 'CO User', 'team' => 'CO']))
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertSet('activeSupportTeamTab', SupportAuthorTeamResolver::TEAM_CO)
            ->assertSee('CO Team')
            ->assertSee('Coach Team')
            ->assertSee('CS Team')
            ->assertSee('기관 지원 보고서')
            ->assertSee('교사 지원 보고서')
            ->assertSee('CO담당')
            ->assertSee('미분류 1건')
            ->assertSet('teamSupportHistory.totals.institution', 4);
    }

    public function test_support_create_link_includes_active_team_menu(): void
    {
        $institution = Institution::query()->create([
            'SKcode' => 'SK-TEAM-CTA',
            'AccountName' => 'CTA 기관',
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->set('activeSupportTeamTab', SupportAuthorTeamResolver::TEAM_CS)
            ->assertSee(route('supports.create', [
                'sk_code' => 'SK-TEAM-CTA',
                'return' => 'institutions',
                'team_menu' => 'cs',
            ], false));
    }

    public function test_reload_after_support_edit_rebuilds_team_history(): void
    {
        $user = User::factory()->create(['name' => 'CO담당', 'is_admin' => false]);

        Employee::query()->create([
            'EMPNO' => 'CO01',
            'KOREANAME' => 'CO담당',
            'WORKDEPT' => 'A02',
        ]);

        $institution = Institution::query()->create([
            'SKcode' => 'SK-REBUILD',
            'AccountName' => '재빌드 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-REBUILD',
            'Account_Name' => '재빌드 기관',
            'CO' => 'CO담당',
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-REBUILD',
            'TR_Name' => 'CO담당',
            'Support_Date' => '2026-04-10',
            'Meet_Time' => '09:00:00',
            'Support_Type' => '방문',
            'Target' => '원장',
            'Issue' => '기존',
            'TO_Account' => '기존',
            'TO_Depart' => '본사',
            'Others' => '',
            'Status' => '진행중',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('openSupportDetailModal', (int) $record->ID)
            ->call('enterSupportDetailEditMode')
            ->set('editIssue', '수정된 이슈')
            ->call('saveSupportDetailEdit')
            ->assertHasNoErrors()
            ->assertSet('teamSupportHistory.co.institution.0.issue', '수정된 이슈');
    }

    public function test_teacher_support_history_always_buckets_under_coach_team(): void
    {
        Employee::query()->create([
            'EMPNO' => 'CO01',
            'KOREANAME' => 'Christie Jung',
            'ENGLISHNAME' => 'Christie Jung',
            'WORKDEPT' => 'A02',
        ]);

        $institution = Institution::query()->create([
            'SKcode' => 'SK-COACH-BUCKET',
            'AccountName' => 'Coach 버킷 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-COACH-BUCKET',
            'Account_Name' => 'Coach 버킷 기관',
            'CO' => 'Christie Jung',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-BUCKET',
            'Name' => '차현주 영어교사',
        ]);

        DB::table('S_Support_NewTeacher')->insert([
            'TR_Name' => 'Christie Jung',
            'SK_Code' => 'SK-COACH-BUCKET',
            'Account_Name' => 'Coach 버킷 기관',
            'Teacher' => '차현주 영어교사',
            'TeacherId' => $teacherId,
            'SupportDate' => '2026-01-03 00:00:00',
            'Status' => '완료',
            'ReportType' => 1,
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertSet('teamSupportHistory.co.teacher', [])
            ->assertCount('teamSupportHistory.coach.teacher', 1)
            ->assertSet('teamSupportHistory.coach.teacher.0.type', '교사 지원(신규교사)')
            ->set('activeSupportTeamTab', SupportAuthorTeamResolver::TEAM_COACH)
            ->assertSee('Christie Jung')
            ->assertSee('차현주 영어교사');
    }

    public function test_teacher_support_history_opens_typed_report_form_in_view_mode(): void
    {
        Employee::query()->create([
            'EMPNO' => 'TR01',
            'KOREANAME' => 'Coach담당',
            'WORKDEPT' => 'A05',
        ]);

        $institution = Institution::query()->create([
            'SKcode' => 'SK-FORM-VIEW',
            'AccountName' => '폼 조회 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-FORM-VIEW',
            'Account_Name' => '폼 조회 기관',
            'TR' => 'Coach담당',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-FORM-VIEW',
            'Name' => '차현주 영어교사',
        ]);

        DB::table('S_Support_NewTeacher')->insert([
            'TR_Name' => 'Coach담당',
            'SK_Code' => 'SK-FORM-VIEW',
            'Account_Name' => '폼 조회 기관',
            'Teacher' => '차현주 영어교사',
            'TeacherId' => $teacherId,
            'SupportDate' => '2026-02-04 00:00:00',
            'Status' => '완료',
            'ReportType' => 1,
        ]);

        $legacyId = (int) DB::table('S_Support_NewTeacher')->max('ID');
        $detailKey = 'legacy:S_Support_NewTeacher:'.$legacyId;

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $teacherId)
            ->assertSet('supportReportViewMode', true)
            ->assertSet('showDemoLessonModal', true)
            ->assertSet('demoLessonForm.teacher_name', '차현주 영어교사')
            ->assertSet('showTeacherSupportHistoryDetailModal', false);
    }
}
