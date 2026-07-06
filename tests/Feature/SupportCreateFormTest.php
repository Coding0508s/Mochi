<?php

namespace Tests\Feature;

use App\Livewire\SupportCreateForm;
use App\Mail\SupportReportStoredMail;
use App\Mail\UrgentSupportNotificationMail;
use App\Models\CoNewTarget;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SupportCreateFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportTables();
        $this->createSfAccountTable();
    }

    private function createSupportTables(): void
    {
        Schema::dropIfExists('S_CO_NewTarget_Detail');
        Schema::dropIfExists('S_CO_NewTarget');
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
        });

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->unsignedInteger('potential_target_id')->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->string('Meet_Time', 50)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->string('Target', 255)->nullable();
            $table->text('Issue')->nullable();
            $table->text('TO_Account')->nullable();
            $table->text('TO_Depart')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
            $table->timestamp('CreatedDate')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->string('record_kind', 20)->nullable();
        });

        Schema::dropIfExists('urgent_support_notifications');
        Schema::create('urgent_support_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('support_record_id');
            $table->unsignedBigInteger('recipient_user_id');
            $table->unsignedBigInteger('sender_user_id');
            $table->string('sk_code', 20)->nullable();
            $table->string('account_name')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('Teachers', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('School_Name', 255)->nullable();
            $table->string('Name', 255)->nullable();
            $table->string('Status', 50)->nullable();
            $table->string('Plan_1st_Support_Date')->nullable();
            $table->string('Plan_2nd_Support_Date')->nullable();
            $table->string('Plan_3rd_Support_Date')->nullable();
            $table->string('Plan_4th_Support_Date')->nullable();
            $table->string('_1st_Support_Date')->nullable();
            $table->string('_2nd_Support_Date')->nullable();
            $table->string('_3rd_Support_Date')->nullable();
            $table->string('_4th_Support_Date')->nullable();
            $table->string('_1st_Support_Type')->nullable();
            $table->string('_2nd_Support_Type')->nullable();
            $table->string('_3rd_Support_Type')->nullable();
            $table->string('_4th_Support_Type')->nullable();
        });

        Schema::dropIfExists('teacher_lva_fb_support_reports');

        Schema::create('teacher_lva_fb_support_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->integer('observe_unit')->nullable();
            $table->integer('observe_lesson')->nullable();
            $table->string('observe_class', 50)->nullable();
            $table->string('observe_age', 50)->nullable();
            $table->string('teacher_experience', 50)->nullable();
            $table->integer('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->text('other_notes')->nullable();
            $table->integer('video_length_minutes')->nullable();
            $table->json('procedures')->nullable();
            $table->json('strength_areas')->nullable();
            $table->json('growth_areas')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('S_CO_NewTarget', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->date('CreatedDate')->nullable();
            $table->string('AccountManager', 100)->nullable();
            $table->string('AccountCode', 100)->nullable();
            $table->string('AccountName', 150);
            $table->string('Address', 255)->nullable();
            $table->string('Director', 100)->nullable();
            $table->string('Phone', 50)->nullable();
            $table->string('Connected', 100)->nullable();
            $table->string('Type', 100)->nullable();
            $table->string('Gubun', 100)->nullable();
            $table->integer('LS')->default(0);
            $table->integer('GS_K')->default(0);
            $table->integer('GS_E')->default(0);
            $table->integer('Total')->default(0);
            $table->integer('Approaching')->default(0);
            $table->integer('Presenting')->default(0);
            $table->integer('Consulting')->default(0);
            $table->integer('Closing')->default(0);
            $table->integer('DroppedOut')->default(0);
            $table->boolean('IsContract')->default(false);
            $table->date('ContractedDate')->nullable();
            $table->string('Possibility', 20)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
        });

        Schema::create('S_CO_NewTarget_Detail', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('AccountName', 150);
            $table->string('AccountManager', 100)->nullable();
            $table->date('MeetingDate');
            $table->string('MeetingTime', 20)->nullable();
            $table->string('MeetingTime_End', 20)->nullable();
            $table->text('Description')->nullable();
            $table->string('ConsultingType', 100)->nullable();
            $table->string('Possibility', 20)->nullable();
        });
    }

    private function createSfAccountTable(): void
    {
        Schema::dropIfExists('SF_Account');
        Schema::create('SF_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('account_ID', 100)->nullable();
            $table->string('Name', 255)->nullable();
            $table->string('GSKR_Billing_Address__c', 255)->nullable();
            $table->string('GSKR_Contract__c', 255)->nullable();
            $table->string('GSKR_Gts_Type__c', 255)->nullable();
        });
    }

    public function test_selecting_institution_fills_default_templates_when_empty(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-TPL-1',
            'AccountName' => '템플릿 테스트 기관',
        ]);

        $user = User::factory()->create();

        $expectedAccount = config('support_report_defaults.to_account_template');
        $expectedDepart = config('support_report_defaults.to_depart_template');

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-TPL-1')
            ->assertSet('formToAccount', $expectedAccount)
            ->assertSet('formToDepart', $expectedDepart);
    }

    public function test_selecting_institution_does_not_overwrite_existing_content(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-TPL-2',
            'AccountName' => '기존 내용 기관',
        ]);

        $user = User::factory()->create();
        $existing = '이미 작성한 소통 내용';

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->set('formToAccount', $existing)
            ->set('formToDepart', '타부서 기존')
            ->call('selectInstitution', 'SK-TPL-2')
            ->assertSet('formToAccount', $existing)
            ->assertSet('formToDepart', '타부서 기존');
    }

    public function test_save_persists_to_account_and_to_depart(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SAVE-1',
            'AccountName' => '저장 테스트',
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-SAVE-1')
            ->set('formToAccount', '기관 소통 본문')
            ->set('formToDepart', '타부서 공유 본문')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-SAVE-1',
            'TO_Account' => '기관 소통 본문',
            'TO_Depart' => '타부서 공유 본문',
        ]);
    }

    public function test_cs_team_sees_issue_toggle_but_co_does_not(): void
    {
        $cs = User::factory()->create(['team' => 'CS']);
        $co = User::factory()->create(['team' => 'CO']);

        Livewire::actingAs($cs)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(SupportCreateForm::class)
            ->assertSeeHtml("setReportMode('issue')");

        Livewire::actingAs($co)
            ->withQueryParams(['team_menu' => 'co'])
            ->test(SupportCreateForm::class)
            ->assertDontSeeHtml("setReportMode('issue')");
    }

    public function test_cs_issue_mode_saves_record_kind_issue_and_redirects_to_institutions(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-ISSUE-1',
            'AccountName' => '이슈 기관',
        ]);

        $cs = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($cs)
            ->withQueryParams(['team_menu' => 'cs', 'report_mode' => 'issue'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'issue')
            ->call('selectInstitution', 'SK-ISSUE-1')
            ->set('formIssue', '앱 접속 불가 이슈')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('institutions.index', ['team_menu' => 'cs']));

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-ISSUE-1',
            'Issue' => '앱 접속 불가 이슈',
            'TO_Account' => null,
            'record_kind' => 'issue',
        ]);
    }

    public function test_cs_issue_mode_cancel_links_to_supports_index(): void
    {
        $cs = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($cs)
            ->withQueryParams(['team_menu' => 'cs', 'report_mode' => 'issue'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'issue')
            ->assertSee(route('supports.index', ['team_menu' => 'cs']), false)
            ->assertDontSee(route('institution-issues.index', ['team_menu' => 'cs']), false);
    }

    public function test_cs_issue_mode_requires_issue_content(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-ISSUE-2',
            'AccountName' => '이슈 기관2',
        ]);

        $cs = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($cs)
            ->withQueryParams(['team_menu' => 'cs', 'report_mode' => 'issue'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-ISSUE-2')
            ->set('formIssue', '')
            ->call('save')
            ->assertHasErrors(['formIssue' => 'required']);
    }

    public function test_cs_issue_mode_urgent_creates_notification(): void
    {
        Mail::fake();

        Institution::query()->create([
            'SKcode' => 'SK-ISSUE-3',
            'AccountName' => '긴급 이슈 기관',
        ]);

        $sender = User::factory()->create(['team' => 'CS']);
        $recipient = User::factory()->create(['team' => 'CS', 'is_active' => true]);

        Livewire::actingAs($sender)
            ->withQueryParams(['team_menu' => 'cs', 'report_mode' => 'issue'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-ISSUE-3')
            ->set('formIssue', '긴급 이슈 본문')
            ->set('isUrgent', true)
            ->set('urgentRecipientIds', [$recipient->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('urgent_support_notifications', [
            'recipient_user_id' => $recipient->id,
            'sender_user_id' => $sender->id,
            'sk_code' => 'SK-ISSUE-3',
        ]);
    }

    public function test_create_form_shows_coach_team_heading_when_team_menu_is_coach(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->assertSee('Coach Team 교사 지원 및 참관 보고서 작성')
            ->assertSee('담당 Coach')
            ->assertDontSee('CO 기관지원보고서 작성');
    }

    public function test_save_redirect_preserves_coach_team_menu(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-COACH-1',
            'AccountName' => 'Coach 팀 저장',
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->assertSet('formTeamMenu', 'coach')
            ->call('setReportMode', 'institution')
            ->call('selectInstitution', 'SK-COACH-1')
            ->set('formToAccount', 'Coach 기관 소통')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('supports.index', ['team_menu' => 'coach']));
    }

    public function test_mount_uses_active_team_menu_when_query_missing(): void
    {
        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->assertSet('formTeamMenu', 'coach');
    }

    public function test_co_team_cannot_switch_to_teacher_report_mode(): void
    {
        $user = User::factory()->create(['team' => 'CO']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'co'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'institution')
            ->assertSet('formCompleted', true)
            ->call('setReportMode', 'teacher')
            ->assertSet('reportMode', 'institution');
    }

    public function test_coach_team_defaults_to_teacher_report_mode(): void
    {
        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'teacher')
            ->assertSet('formCompleted', false)
            ->assertSet('formCoachTeacherCreateAction', 'visit')
            ->assertSee('Coach Team 교사 지원 및 참관 보고서 작성');
    }

    public function test_completion_default_updates_when_switching_report_modes(): void
    {
        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'teacher')
            ->assertSet('formCompleted', false)
            ->call('setReportMode', 'institution')
            ->assertSet('reportMode', 'institution')
            ->assertSet('formCompleted', true)
            ->call('setReportMode', 'teacher')
            ->assertSet('reportMode', 'teacher')
            ->assertSet('formCompleted', false);
    }

    public function test_coach_team_teacher_report_mode_stays_on_support_form_page(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-COACH-TYPES',
            'AccountName' => 'Coach 유형 선택 기관',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-TYPES',
            'Name' => '김교사',
        ]);

        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'teacher')
            ->assertSee('Coach Team 교사 지원 및 참관 보고서 작성')
            ->assertSee('아래 입력 항목을 작성한 뒤 저장해 주세요.')
            ->assertSee('교사 지원 및 참관 보고서')
            ->call('selectInstitution', 'SK-COACH-TYPES')
            ->assertSee('교사를 선택하세요')
            ->set('formTeacherId', $teacherId)
            ->assertNoRedirect()
            ->assertSet('formSupportType', '교사 지원 및 참관')
            ->assertSet('formCoachTeacherCreateAction', 'visit')
            ->assertSet('visitTeacherId', $teacherId)
            ->assertDontSee('교사 지원 유형 선택')
            ->assertSee('세부 지원 내용')
            ->assertDontSee('기관 이슈 및 논의 사항')
            ->assertSet('formTarget', '김교사');
    }

    public function test_support_method_selects_sync_on_change(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SUPPORT-METHOD',
            'AccountName' => '지원 방법 동기화 기관',
        ]);

        $user = User::factory()->create(['team' => 'CO']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'co'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-SUPPORT-METHOD')
            ->assertSee('wire:key="support-create-standard-report"', false)
            ->assertSee('wire:model.change.live="formSupportType"', false)
            ->assertSee('<option value="대면">대면</option>', false);
    }

    public function test_coach_teacher_selection_accepts_livewire_string_teacher_id(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-COACH-STRING-ID',
            'AccountName' => 'Coach 문자열 ID 기관',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-STRING-ID',
            'Name' => '문자열교사',
        ]);

        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-STRING-ID')
            ->set('formTeacherId', (string) $teacherId)
            ->assertSet('formTeacherId', $teacherId)
            ->assertSet('visitTeacherId', $teacherId)
            ->assertSet('formTarget', '문자열교사');
    }

    public function test_coach_teacher_selection_ignores_non_numeric_livewire_teacher_id(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-COACH-BAD-ID',
            'AccountName' => 'Coach 잘못된 ID 기관',
        ]);

        DB::table('Teachers')->insert([
            'SK_Code' => 'SK-COACH-BAD-ID',
            'Name' => '맨일',
        ]);

        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-BAD-ID')
            ->set('formTeacherId', '맨일')
            ->assertSet('formTeacherId', null)
            ->assertSet('visitTeacherId', null)
            ->assertSet('formTarget', '');
    }

    public function test_coach_team_support_round_defaults_to_year_matched_plan_round(): void
    {
        $year = now()->year;

        Institution::query()->create([
            'SKcode' => 'SK-COACH-ROUND',
            'AccountName' => 'Coach 차시 추천 기관',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-ROUND',
            'Name' => '추천교사',
            'Plan_1st_Support_Date' => ($year - 1).'-03-01',
            'Plan_2nd_Support_Date' => $year.'-05-01',
        ]);

        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-ROUND')
            ->set('formTeacherId', $teacherId)
            ->assertSet('supportRound', '2');
    }

    public function test_coach_team_support_round_select_shows_all_rounds_with_year_recommendation_label(): void
    {
        $year = now()->year;

        Institution::query()->create([
            'SKcode' => 'SK-COACH-ROUND-LABEL',
            'AccountName' => 'Coach 차시 라벨 기관',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-ROUND-LABEL',
            'Name' => '라벨교사',
            'Plan_3rd_Support_Date' => $year.'-08-10',
        ]);

        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-ROUND-LABEL')
            ->set('formTeacherId', $teacherId)
            ->set('formCompleted', true)
            ->assertSee('1차')
            ->assertSee('2차')
            ->assertSee('3차 (해당 연도 계획)')
            ->assertSee('4차')
            ->assertSee('기준 연도 '.$year);
    }

    public function test_coach_teacher_selector_excludes_retired_teacher(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-COACH-RET',
            'AccountName' => 'Coach 퇴직 제외 기관',
        ]);

        DB::table('Teachers')->insert([
            'SK_Code' => 'SK-COACH-RET',
            'Name' => '재직 교사',
            'Status' => '재직',
        ]);

        DB::table('Teachers')->insert([
            'SK_Code' => 'SK-COACH-RET',
            'Name' => '퇴직 교사',
            'Status' => '퇴직',
        ]);

        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-RET')
            ->assertSee('재직 교사')
            ->assertDontSee('퇴직 교사');
    }

    public function test_coach_teacher_selector_rejects_retired_teacher_selection(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-COACH-RET-ERR',
            'AccountName' => 'Coach 퇴직 안내 기관',
        ]);

        $retiredTeacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-RET-ERR',
            'Name' => '퇴직 대상 교사',
            'Status' => '퇴직',
        ]);

        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-RET-ERR')
            ->set('formTeacherId', $retiredTeacherId)
            ->assertSet('formTeacherId', null)
            ->assertHasErrors(['formTeacherId'])
            ->assertSee('퇴직 교사는 선택할 수 없습니다.');
    }

    public function test_coach_team_can_save_visit_report_from_support_create_form(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['visit-form@test.org'],
        ]);

        Institution::query()->create([
            'SKcode' => 'SK-COACH-LVA',
            'AccountName' => 'Coach LVA 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-COACH-LVA',
            'Account_Name' => 'Coach LVA 기관',
            'TR' => 'Coach A',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-LVA',
            'Name' => '김교사',
        ]);

        $user = User::factory()->admin()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('setReportMode', 'teacher')
            ->call('selectInstitution', 'SK-COACH-LVA')
            ->set('formTeacherId', $teacherId)
            ->assertSet('visitTeacherId', $teacherId)
            ->set('formCompleted', true)
            ->set('visitForm.support_purpose', '정기 참관')
            ->set('visitForm.monitoring_feedback', '수업 모니터링 내용')
            ->set('visitForm.interview_and_action_plan', '후속 조치 계획')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_visit_support_reports', [
            'teacher_id' => $teacherId,
            'teacher_name' => '김교사',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-COACH-LVA',
            'Support_Type' => '교사 지원 및 참관',
            'Status' => '완료',
        ]);

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            '_1st_Support_Type' => '교사 지원 및 참관',
        ]);

        Mail::assertNothingSent();
    }

    public function test_coach_team_visit_form_commits_required_inputs_on_blur(): void
    {
        // 지연(deferred) wire:model은 조건부 재렌더 시 입력값이 서버로 전달되지 않을 수 있어
        // 필수 입력칸은 wire:model.blur로 즉시 커밋해야 한다. (DOM 재사용/미전송 회귀 방지)
        Institution::query()->create([
            'SKcode' => 'SK-COACH-BLUR',
            'AccountName' => 'Coach Blur 기관',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-BLUR',
            'Name' => '최교사',
        ]);

        $user = User::factory()->admin()->create(['team' => 'COACH']);

        $component = Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-BLUR')
            ->set('formTeacherId', $teacherId)
            ->assertSet('visitTeacherId', $teacherId);

        $component->assertSeeHtml('wire:model.blur="visitForm.support_purpose"');
        $component->assertSeeHtml('wire:model.blur="visitForm.monitoring_feedback"');
        $component->assertSeeHtml('wire:key="visit-report-basic-fields"');
        $component->assertSeeHtml('wire:key="visit-report-monitoring-feedback"');
    }

    public function test_coach_team_complete_visit_save_shows_alert_when_required_fields_missing(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-COACH-VAL',
            'AccountName' => 'Coach 검증 기관',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-VAL',
            'Name' => '박교사',
        ]);

        $user = User::factory()->admin()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-VAL')
            ->set('formTeacherId', $teacherId)
            ->set('formCompleted', true)
            ->set('visitForm.support_purpose', '정기 참관')
            ->call('save')
            ->assertHasErrors([
                'visitForm.monitoring_feedback',
            ])
            ->assertDispatched('visit-support-show-alert');

        $this->assertDatabaseCount('teacher_visit_support_reports', 0);
    }

    public function test_coach_team_in_progress_visit_report_creates_in_progress_support_record(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['visit-form@test.org'],
        ]);

        Institution::query()->create([
            'SKcode' => 'SK-COACH-DRAFT',
            'AccountName' => 'Coach 임시 기관',
        ]);

        $teacherId = (int) DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK-COACH-DRAFT',
            'Name' => '이교사',
        ]);

        $user = User::factory()->admin()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-DRAFT')
            ->set('formTeacherId', $teacherId)
            ->assertSet('formCompleted', false)
            ->set('visitForm.support_purpose', '정기 참관')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_visit_support_reports', [
            'teacher_id' => $teacherId,
            'status' => '임시',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-COACH-DRAFT',
            'Support_Type' => '교사 지원 및 참관',
            'Status' => '진행중',
        ]);

        Mail::assertNothingSent();
    }

    public function test_coach_teacher_report_mode_shows_visit_form_guidance_before_save(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-COACH-REQ',
            'AccountName' => 'Coach 필수 기관',
        ]);

        $user = User::factory()->create(['team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach', 'report_mode' => 'teacher'])
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-COACH-REQ')
            ->set('formToAccount', '교사 소통 본문')
            ->call('save')
            ->assertHasErrors(['formTeacherId']);
    }

    public function test_cs_team_defaults_to_issue_report_mode(): void
    {
        $user = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'issue');
    }

    public function test_cs_team_can_open_institution_report_mode_via_query(): void
    {
        $user = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs', 'report_mode' => 'institution'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'institution')
            ->assertSet('formCompleted', true);
    }

    public function test_cs_team_cannot_use_teacher_report_mode(): void
    {
        $user = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'issue')
            ->assertDontSeeHtml("setReportMode('teacher')")
            ->call('setReportMode', 'teacher')
            ->assertSet('reportMode', 'issue');
    }

    public function test_cs_team_with_teacher_query_param_falls_back_to_issue(): void
    {
        $user = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs', 'report_mode' => 'teacher'])
            ->test(SupportCreateForm::class)
            ->assertSet('reportMode', 'issue');
    }

    public function test_save_does_not_send_mail_when_institution_report_not_completed(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['group@test.org'],
        ]);

        Institution::query()->create([
            'SKcode' => 'SK-MAIL-INPROG',
            'AccountName' => '진행중 메일 테스트',
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-MAIL-INPROG')
            ->set('formCompleted', false)
            ->set('formToAccount', '기관 소통 본문')
            ->call('save')
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_save_sends_mail_when_support_report_notify_addresses_configured(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['group@test.org', 'backup@test.org'],
        ]);

        Institution::query()->create([
            'SKcode' => 'SK-MAIL-1',
            'AccountName' => '메일 테스트 기관',
        ]);

        $user = User::factory()->create(['name' => '작성자', 'email' => 'author@example.com']);

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-MAIL-1')
            ->set('formToAccount', '기관 소통 본문')
            ->set('formToDepart', '타부서 공유 본문')
            ->call('save')
            ->assertHasNoErrors();

        Mail::assertSent(SupportReportStoredMail::class, function (SupportReportStoredMail $mail): bool {
            return $mail->hasTo('group@test.org')
                && $mail->hasTo('backup@test.org');
        });
    }

    public function test_save_sends_mail_with_coach_team_labels_when_team_menu_is_coach(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['coach-notify@test.org'],
        ]);

        Institution::query()->create([
            'SKcode' => 'SK-COACH-MAIL',
            'AccountName' => 'Coach 메일 기관',
        ]);

        $user = User::factory()->create(['name' => 'TEST', 'email' => 'coach@example.com', 'team' => 'COACH']);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(SupportCreateForm::class)
            ->call('setReportMode', 'institution')
            ->call('selectInstitution', 'SK-COACH-MAIL')
            ->set('formToAccount', 'Coach 팀 소통')
            ->call('save')
            ->assertHasNoErrors();

        Mail::assertSent(SupportReportStoredMail::class, function (SupportReportStoredMail $mail): bool {
            return $mail->reportSavedOpening === 'Coach Team 기관 지원 보고서'
                && $mail->reportAssigneeColumnLabel === 'Coach'
                && $mail->envelope()->subject === '[Coach Team 기관 지원 보고서] Coach 메일 기관';
        });
    }

    public function test_save_does_not_send_mail_when_notify_addresses_empty(): void
    {
        Mail::fake();

        config(['support_report_mail.notify_addresses' => []]);

        Institution::query()->create([
            'SKcode' => 'SK-NO-MAIL',
            'AccountName' => '메일 없음',
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-NO-MAIL')
            ->set('formToAccount', '내용')
            ->call('save')
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_urgent_save_creates_notifications_and_sends_urgent_mails(): void
    {
        Mail::fake();

        config(['support_report_mail.notify_addresses' => []]);

        Institution::query()->create([
            'SKcode' => 'SK-URGENT-1',
            'AccountName' => '긴급 알림 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-URGENT-1',
            'Account_Name' => '긴급 알림 기관',
            'CO' => 'co-user',
            'TR' => 'tr-user',
            'CS' => 'cs-user',
        ]);

        $sender = User::factory()->create(['name' => 'sender-user', 'email' => 'sender@example.com']);
        $co = User::factory()->create(['name' => 'co-user', 'email' => 'co@example.com', 'is_active' => true]);
        $tr = User::factory()->create(['name' => 'tr-user', 'email' => 'tr@example.com', 'is_active' => true]);
        $cs = User::factory()->create(['name' => 'cs-user', 'email' => 'cs@example.com', 'is_active' => true]);

        Livewire::actingAs($sender)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-URGENT-1')
            ->set('isUrgent', true)
            ->set('formToAccount', '긴급 공지 내용')
            ->set('formToDepart', '본사 공유')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-URGENT-1',
            'is_urgent' => true,
        ]);

        $this->assertDatabaseCount('urgent_support_notifications', 3);
        $this->assertDatabaseHas('urgent_support_notifications', [
            'recipient_user_id' => $co->id,
            'sender_user_id' => $sender->id,
        ]);
        $this->assertDatabaseHas('urgent_support_notifications', [
            'recipient_user_id' => $tr->id,
            'sender_user_id' => $sender->id,
        ]);
        $this->assertDatabaseHas('urgent_support_notifications', [
            'recipient_user_id' => $cs->id,
            'sender_user_id' => $sender->id,
        ]);

        Mail::assertSent(UrgentSupportNotificationMail::class, 3);
    }

    public function test_save_mirrors_to_potential_detail_for_uncontracted_target(): void
    {
        config(['potential_institutions.show_support_report_ui' => true]);

        Institution::query()->create([
            'SKcode' => 'SK-POT-1',
            'AccountName' => '잠재 기관',
        ]);

        $user = User::factory()->create(['name' => '테스터']);

        CoNewTarget::query()->create([
            'AccountCode' => 'SK-POT-1',
            'AccountName' => '잠재 기관',
            'AccountManager' => 'CO 담당자',
            'IsContract' => false,
            'Possibility' => 'B',
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-POT-1', true)
            ->set('formSupportDate', '2026-04-11')
            ->set('formSupportTime', '14:30')
            ->set('formSupportType', '전화')
            ->set('formToAccount', '기관 소통 내용')
            ->set('formToDepart', '타부서 공유 내용')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'AccountName' => '잠재 기관',
            'AccountManager' => 'CO 담당자',
            'MeetingDate' => '2026-04-11 00:00:00',
            'MeetingTime' => '14:30',
            'ConsultingType' => '전화',
            'Possibility' => 'B',
        ]);
    }

    public function test_save_does_not_mirror_to_potential_detail_for_contracted_target(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CONTRACT-1',
            'AccountName' => '계약 완료 기관',
        ]);

        CoNewTarget::query()->create([
            'AccountCode' => 'SK-CONTRACT-1',
            'AccountName' => '계약 완료 기관',
            'AccountManager' => 'CO 담당자',
            'IsContract' => true,
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-CONTRACT-1')
            ->set('formSupportDate', '2026-04-11')
            ->set('formSupportTime', '10:10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('S_CO_NewTarget_Detail', [
            'AccountName' => '계약 완료 기관',
            'MeetingDate' => '2026-04-11',
        ]);
    }

    public function test_save_for_uncontracted_potential_without_sk_records_potential_target_id(): void
    {
        config(['potential_institutions.show_support_report_ui' => true]);

        $user = User::factory()->create(['name' => '테스터']);

        $potential = CoNewTarget::query()->create([
            'AccountCode' => null,
            'AccountName' => '무SK 잠재 기관',
            'AccountManager' => '잠재 담당자',
            'IsContract' => false,
            'Possibility' => 'C',
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', '', true, (int) $potential->ID)
            ->set('formSupportDate', '2026-04-21')
            ->set('formSupportTime', '15:10')
            ->set('formSupportType', '대면')
            ->set('formToAccount', '무SK 잠재기관 소통')
            ->set('formToDepart', '내부 공유')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'potential_target_id' => (int) $potential->ID,
            'SK_Code' => null,
            'Account_Name' => '무SK 잠재 기관',
            'Support_Type' => '대면',
        ]);

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'AccountName' => '무SK 잠재 기관',
            'AccountManager' => '잠재 담당자',
            'MeetingDate' => '2026-04-21 00:00:00',
            'MeetingTime' => '15:10',
            'ConsultingType' => '대면',
            'Possibility' => 'C',
        ]);
    }

    public function test_non_creator_cannot_select_uncontracted_potential_target(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['name' => '다른 사용자']);
        $potential = CoNewTarget::query()->create([
            'AccountCode' => null,
            'AccountName' => '타인 잠재 기관',
            'AccountManager' => '잠재 담당자',
            'IsContract' => false,
            'Possibility' => 'C',
            'created_by' => $owner->id,
        ]);

        Livewire::actingAs($other)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', '', true, (int) $potential->ID)
            ->assertSet('formPotentialTargetId', null)
            ->assertSet('formIsPotential', false);
    }

    public function test_save_rejects_sf_upload_for_uncontracted_potential_without_sk(): void
    {
        config(['potential_institutions.show_support_report_ui' => true]);

        $user = User::factory()->create(['name' => '테스터']);

        $potential = CoNewTarget::query()->create([
            'AccountCode' => null,
            'AccountName' => '무SK 파일제한 기관',
            'AccountManager' => '잠재 담당자',
            'IsContract' => false,
            'Possibility' => 'B',
            'created_by' => $user->id,
        ]);

        Storage::fake('local');
        $upload = UploadedFile::fake()->create('무sk-업로드.pdf', 100, 'application/pdf');

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', '', true, (int) $potential->ID)
            ->set('formSupportDate', '2026-04-22')
            ->set('formSupportTime', '11:20')
            ->set('sfUpload', $upload)
            ->call('save')
            ->assertHasErrors(['sfUpload']);

        $this->assertDatabaseCount('contract_documents', 0);
        $this->assertDatabaseCount('SF_Files', 0);
    }

    public function test_save_with_sf_upload_creates_contract_document_and_sf_file_with_account_prefix(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SF-1',
            'AccountName' => 'SF 업로드 기관',
        ]);

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => 'SF 업로드 기관',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        Storage::fake('local');
        $user = User::factory()->create(['name' => '업로더']);
        $upload = UploadedFile::fake()->create('지원자료.pdf', 120, 'application/pdf');

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-SF-1')
            ->set('formSupportDate', '2026-04-11')
            ->set('formSupportTime', '10:10')
            ->set('sfUpload', $upload)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-SF-1',
            'Account_Name' => 'SF 업로드 기관',
        ]);

        $document = DB::table('contract_documents')
            ->where('sk_code', 'SK-SF-1')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($document);
        $this->assertSame('지원자료.pdf', $document->original_filename);
        Storage::disk('local')->assertExists((string) $document->stored_path);

        $sfFile = DB::table('SF_Files')
            ->where('fileName', 'like', '%지원자료.pdf')
            ->orderByDesc('ID')
            ->first();
        $this->assertNotNull($sfFile);
        $this->assertStringStartsWith('0015i00000oOSBqAAO_', (string) $sfFile->fileName);
    }

    public function test_save_with_sf_upload_falls_back_to_original_filename_when_account_not_found(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SF-2',
            'AccountName' => '매칭없음 기관',
        ]);

        Storage::fake('local');
        $user = User::factory()->create(['name' => '업로더2']);
        $upload = UploadedFile::fake()->create('원본파일.pdf', 90, 'application/pdf');

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-SF-2')
            ->set('formSupportDate', '2026-04-12')
            ->set('formSupportTime', '11:30')
            ->set('sfUpload', $upload)
            ->call('save')
            ->assertHasNoErrors();

        $sfFile = DB::table('SF_Files')
            ->where('fileName', '원본파일.pdf')
            ->orderByDesc('ID')
            ->first();

        $this->assertNotNull($sfFile);
    }

    public function test_mount_prefills_institution_sk_code_from_query(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-PREFILL',
            'AccountName' => '프리필 운영기관',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->withQueryParams(['sk_code' => 'SK-PREFILL', 'return' => 'institutions'])
            ->test(SupportCreateForm::class)
            ->assertSet('formSkCode', 'SK-PREFILL')
            ->assertSet('formAccountName', '프리필 운영기관')
            ->assertSet('formIsPotential', false)
            ->assertSet('afterSaveRouteName', 'institutions.index');
    }

    public function test_mount_does_not_prefill_terminated_institution_sk_code(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-TERM',
            'AccountName' => '해지 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-TERM',
            'Account_Name' => '해지 기관',
            'Customer_Type' => '해지',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->withQueryParams(['sk_code' => 'SK-TERM'])
            ->test(SupportCreateForm::class)
            ->assertSet('formSkCode', '')
            ->assertSet('formAccountName', '');
    }

    public function test_select_institution_blocks_terminated_customer(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-TERM-SELECT',
            'AccountName' => '선택 차단 해지 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-TERM-SELECT',
            'Account_Name' => '선택 차단 해지 기관',
            'Customer_Type' => '해지',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-TERM-SELECT')
            ->assertSet('formSkCode', '')
            ->assertSet('formAccountName', '');
    }

    public function test_save_redirects_to_institution_list_when_return_is_institutions(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-RETURN',
            'AccountName' => '복귀 테스트',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->withQueryParams(['sk_code' => 'SK-RETURN', 'return' => 'institutions'])
            ->test(SupportCreateForm::class)
            ->set('formSupportDate', '2026-05-18')
            ->set('formSupportTime', '10:30')
            ->set('formSupportType', '전화')
            ->set('formToAccount', '소통 내용')
            ->call('save')
            ->assertRedirect(route('institutions.index', ['team_menu' => 'co']));

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-RETURN',
            'Account_Name' => '복귀 테스트',
        ]);
    }

    public function test_mount_prefills_potential_target_from_parameter(): void
    {
        config(['potential_institutions.show_support_report_ui' => true]);

        $user = User::factory()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => 'Mgr',
            'AccountCode' => null,
            'AccountName' => '프리필 잠재',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'Possibility' => 'C',
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class, ['potentialTargetId' => (int) $target->ID])
            ->assertSet('formPotentialTargetId', (int) $target->ID)
            ->assertSet('formAccountName', '프리필 잠재')
            ->assertSet('formIsPotential', true)
            ->assertSet('formPossibility', 'C');
    }

    public function test_mount_does_not_prefill_contracted_potential_target(): void
    {
        $user = User::factory()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => null,
            'AccountCode' => 'SK-X',
            'AccountName' => '계약됨',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => true,
            'Possibility' => null,
        ]);

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class, ['potentialTargetId' => (int) $target->ID])
            ->assertSet('formPotentialTargetId', null);
    }
}
