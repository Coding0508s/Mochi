<?php

namespace Tests\Feature;

use App\Jobs\ProcessAssignmentChangeRequestsJob;
use App\Jobs\SyncInstitutionOutboundJob;
use App\Livewire\InstitutionList;
use App\Models\AccountInformation;
use App\Models\AssignmentChangeRequest;
use App\Models\GsNumber;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('features.institution_create_enabled', true);
        Config::set('services.institution_outbound.enabled', false);
        Queue::fake();
        $this->createAccountTables();
        $this->createSkCodeRequestsTable();
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
    }

    private function createSkCodeRequestsTable(): void
    {
        Schema::dropIfExists('sk_code_requests');

        Schema::create('sk_code_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('co_new_target_id');
            $table->string('institution_name', 200);
            $table->string('temp_sk_code', 64);
            $table->string('final_sk_code', 64)->nullable();
            $table->string('portal_campus_id', 100)->nullable();
            $table->string('account_no', 100)->nullable();
            $table->string('co', 255)->nullable();
            $table->string('tr', 255)->nullable();
            $table->string('cs', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_institution_list_is_driven_by_account_information_table(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-ONLY-MASTER',
            'AccountName' => '마스터만 있는 기관',
        ]);

        AccountInformation::query()->create([
            'SK_Code' => 'SK1894',
            'Account_Name' => '수원 장안 성민유치원',
            'TR' => 'Jeanie Park',
            'CS' => 'Bella Joo',
            'CO' => 'Daniel Kim',
            'Customer_Type' => 'GTS 13 기존',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(InstitutionList::class)
            ->assertSee('수원 장안 성민유치원')
            ->assertSee('Jeanie Park')
            ->assertSee('Daniel Kim')
            ->assertSee('마스터만 있는 기관');
    }

    public function test_detail_modal_shows_support_report_create_link_for_active_institution(): void
    {
        $institution = Institution::query()->create([
            'SKcode' => 'SK-REPORT-LINK',
            'AccountName' => '지원보고서 링크 기관',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertSee('지원보고서 작성')
            ->assertSee(route('supports.create', ['sk_code' => 'SK-REPORT-LINK', 'return' => 'institutions'], false));
    }

    public function test_detail_modal_hides_support_report_create_link_for_terminated_institution(): void
    {
        $institution = Institution::query()->create([
            'SKcode' => 'SK-TERM-LINK',
            'AccountName' => '해지 링크 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-TERM-LINK',
            'Account_Name' => '해지 링크 기관',
            'Customer_Type' => 'GTS 16 Conversion 해지',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertSee('해지 기관은 신규 지원보고서 작성이 제한됩니다')
            ->assertDontSee(route('supports.create', ['sk_code' => 'SK-TERM-LINK', 'return' => 'institutions'], false));
    }

    public function test_author_can_edit_support_detail_from_institution_modal(): void
    {
        $user = User::factory()->create(['name' => '지원작성자', 'is_admin' => false]);
        $institution = Institution::query()->create([
            'SKcode' => 'SK-SUP-EDIT',
            'AccountName' => '지원 수정 기관',
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-SUP-EDIT',
            'Account_Name' => '지원 수정 기관',
            'TR_Name' => '지원작성자',
            'Support_Date' => '2026-04-10',
            'Meet_Time' => '09:00:00',
            'Support_Type' => '방문',
            'Target' => '원장',
            'Issue' => '기존 이슈',
            'TO_Account' => '기존 소통',
            'TO_Depart' => '기존 본사',
            'Others' => '기존 기타',
            'Status' => '진행중',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('openSupportDetailModal', (int) $record->ID)
            ->assertSet('selectedSupportRecord.support_time', '09:00')
            ->assertSee('지원 내역 보기 모드')
            ->call('enterSupportDetailEditMode')
            ->assertSet('editSupportTime', '09:00')
            ->set('editSupportDate', '2026-05-01')
            ->set('editSupportTime', '14:00')
            ->set('editSupportType', '전화')
            ->set('editTarget', '교사')
            ->set('editIssue', '수정 이슈')
            ->set('editToAccount', '수정 소통')
            ->set('editToDepart', '수정 본사')
            ->set('editOthers', '수정 기타')
            ->set('editCompleted', true)
            ->call('saveSupportDetailEdit')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertSame('전화', $record->Support_Type);
        $this->assertSame('수정 이슈', $record->Issue);
        $this->assertSame('완료', $record->Status);
        $this->assertNotNull($record->CompletedDate);
    }

    public function test_non_author_cannot_save_support_detail_edit(): void
    {
        $author = User::factory()->create(['name' => '작성자A', 'is_admin' => false]);
        $other = User::factory()->create(['name' => '타인B', 'is_admin' => false]);
        $institution = Institution::query()->create([
            'SKcode' => 'SK-SUP-DENY',
            'AccountName' => '권한 테스트',
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-SUP-DENY',
            'Account_Name' => '권한 테스트',
            'TR_Name' => '작성자A',
            'Support_Date' => '2026-04-10',
            'Meet_Time' => '10:00:00',
            'Support_Type' => '방문',
        ]);

        Livewire::actingAs($other)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('openSupportDetailModal', (int) $record->ID)
            ->assertDontSee('지원 내역 보기 모드')
            ->set('editSupportDate', '2026-05-01')
            ->set('editSupportTime', '11:00')
            ->set('editSupportType', '전화')
            ->call('saveSupportDetailEdit')
            ->assertHasErrors('supportDetailEdit');
    }

    public function test_admin_can_delete_support_detail_from_institution_modal(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $institution = Institution::query()->create([
            'SKcode' => 'SK-SUP-DEL',
            'AccountName' => '삭제 테스트',
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-SUP-DEL',
            'Account_Name' => '삭제 테스트',
            'TR_Name' => '관리자',
            'Support_Date' => '2026-04-10',
            'Support_Type' => '방문',
        ]);

        Livewire::actingAs($admin)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('openSupportDetailModal', (int) $record->ID)
            ->call('deleteSupportDetail');

        $this->assertDatabaseMissing('S_SupportInfo_Account', [
            'ID' => $record->ID,
        ]);
    }

    public function test_terminated_institution_hides_support_detail_edit_toggle(): void
    {
        $user = User::factory()->create(['name' => '지원작성자', 'is_admin' => false]);
        $institution = Institution::query()->create([
            'SKcode' => 'SK-SUP-TERM',
            'AccountName' => '해지 지원 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-SUP-TERM',
            'Account_Name' => '해지 지원 기관',
            'Customer_Type' => 'GTS 16 Conversion 해지',
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-SUP-TERM',
            'Account_Name' => '해지 지원 기관',
            'TR_Name' => '지원작성자',
            'Support_Date' => '2026-04-10',
            'Support_Type' => '방문',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('openSupportDetailModal', (int) $record->ID)
            ->assertDontSee('지원 내역 보기 모드')
            ->assertSee('해지 기관의 지원 내역은 수정·삭제할 수 없습니다');
    }

    public function test_co_team_user_can_update_only_co_field_in_detail_modal(): void
    {
        $this->insertEmployee('E-CO-EDIT', 'A02', 'Peter Kim', 1);
        $this->insertEmployee('E-CO-OTHER', 'A02', 'Rami Lee', 1);

        $user = User::factory()->create([
            'name' => 'Peter Kim',
            'email' => 'peter@example.com',
            'employee_empno' => 'E-CO-EDIT',
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $institution = Institution::query()->create([
            'SKcode' => 'SK-CO-ONLY',
            'AccountName' => 'CO 팀 수정 테스트',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-CO-ONLY',
            'Account_Name' => 'CO 팀 수정 테스트',
            'CO' => 'Peter Kim',
            'TR' => 'Keep TR',
            'CS' => 'Keep CS',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editDetailCo', 'Rami Lee')
            ->set('editDetailTr', 'Hacked TR')
            ->set('editDetailInstitutionName', 'Hacked Name')
            ->call('saveDetailFields')
            ->assertHasNoErrors()
            ->assertSet('showDetailModal', false);

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-CO-ONLY',
            'CO' => 'Rami Lee',
            'TR' => 'Keep TR',
            'CS' => 'Keep CS',
        ]);
        $this->assertDatabaseHas('S_AccountName', [
            'ID' => $institution->ID,
            'AccountName' => 'CO 팀 수정 테스트',
        ]);
    }

    public function test_user_without_team_cannot_save_institution_detail(): void
    {
        $user = User::factory()->create([
            'team' => '',
            'is_admin' => false,
        ]);

        $institution = Institution::query()->create([
            'SKcode' => 'SK-NO-TEAM',
            'AccountName' => '팀 없음',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertDontSee('wire:click="startDetailEdit"', false)
            ->call('startDetailEdit')
            ->set('editDetailCo', 'Should Fail')
            ->call('saveDetailFields')
            ->assertHasErrors('detailEdit');
    }

    public function test_index_renders_and_has_no_inline_register_button(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('institutions.index'))
            ->assertOk()
            ->assertSee('기관리스트')
            ->assertDontSee('신규 기관 등록');
    }

    public function test_legacy_open_create_query_does_not_show_create_ui(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('institutions.index', ['openCreate' => 1]))
            ->assertOk()
            ->assertDontSee('신규 기관 생성');
    }

    public function test_hidden_institution_is_not_visible_on_list(): void
    {
        $user = User::factory()->create();

        Institution::query()->create([
            'SKcode' => 'SK-VISIBLE-1',
            'AccountName' => '표시 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-HIDDEN-1',
            'AccountName' => '숨김 기관',
        ]);

        AccountInformation::query()->create([
            'SK_Code' => 'SK-VISIBLE-1',
            'Account_Name' => '표시 기관',
        ]);
        AccountInformation::query()->create([
            'SK_Code' => 'SK-HIDDEN-1',
            'Account_Name' => '숨김 기관',
        ]);

        DB::table('institution_visibility_overrides')->insert([
            'sk_code' => 'SK-HIDDEN-1',
            'hidden_reason' => 'uncontracted',
            'hidden_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('institutions.index'))
            ->assertOk()
            ->assertSee('표시 기관')
            ->assertDontSee('숨김 기관');
    }

    public function test_co_team_user_sees_only_assigned_institutions(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CO-1',
            'AccountName' => '담당 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-CO-2',
            'AccountName' => '비담당 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-CO-1',
                'Account_Name' => '담당 기관',
                'CO' => 'Peter Kim',
            ],
            [
                'SK_Code' => 'SK-CO-2',
                'Account_Name' => '비담당 기관',
                'CO' => 'James Kwak',
            ],
        ]);

        $coUser = User::factory()->create([
            'name' => 'Peter Kim',
            'email' => 'peter.kim@grapeseed.com',
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $this->actingAs($coUser)
            ->get(route('institutions.index'))
            ->assertOk()
            ->assertSee('담당 기관')
            ->assertDontSee('비담당 기관');
    }

    public function test_co_team_user_sees_institution_when_co_uses_dotted_english_name(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CO-DOTTED-1',
            'AccountName' => '점 표기 담당 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-CO-DOTTED-2',
            'AccountName' => '다른 점 표기 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-CO-DOTTED-1',
                'Account_Name' => '점 표기 담당 기관',
                'CO' => 'Peter.Kim',
            ],
            [
                'SK_Code' => 'SK-CO-DOTTED-2',
                'Account_Name' => '다른 점 표기 기관',
                'CO' => 'James.Kwak',
            ],
        ]);

        DB::table('employee')->insert([
            'EMPNO' => 'E-PETER',
            'WORKDEPT' => 'A02',
            'KOREANAME' => '김봉철',
            'ENGLISHNAME' => 'Peter Kim',
            'EMAIL' => 'peter.kim@grapeseed.com',
            'STATUS' => 1,
        ]);

        $coUser = User::factory()->create([
            'name' => '김봉철',
            'email' => 'peter.kim@grapeseed.com',
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $this->actingAs($coUser)
            ->get(route('institutions.index'))
            ->assertOk()
            ->assertSee('점 표기 담당 기관')
            ->assertDontSee('다른 점 표기 기관');
    }

    public function test_detail_modal_aligns_dotted_co_to_master_option_label(): void
    {
        // DB에는 점 표기로 저장되어 있어도, 직원 마스터에 같은 사람이 공백 표기로 등록되어
        // 있다면 상세 모달 진입 시점에 옵션 표기로 정렬돼야 "미지정"으로 표시되지 않는다.
        $institution = Institution::query()->create([
            'SKcode' => 'SK-ALIGN-1',
            'AccountName' => '정렬 대상 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-ALIGN-1',
            'Account_Name' => '정렬 대상 기관',
            'CO' => 'Peter.Kim',
            'TR' => 'Rami.Lee',
            'CS' => 'Bella.Joo',
        ]);

        DB::table('employee')->insert([
            ['EMPNO' => 'E-PETER', 'WORKDEPT' => 'A02', 'KOREANAME' => '김봉철', 'ENGLISHNAME' => 'Peter Kim', 'STATUS' => 1],
            ['EMPNO' => 'E-RAMI', 'WORKDEPT' => 'A05', 'KOREANAME' => '이라미', 'ENGLISHNAME' => 'Rami Lee', 'STATUS' => 1],
            ['EMPNO' => 'E-BELLA', 'WORKDEPT' => 'A03', 'KOREANAME' => '주벨라', 'ENGLISHNAME' => 'Bella Joo', 'STATUS' => 1],
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin.align@grapeseed.com',
            'team' => 'CO',
            'is_admin' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertSet('editDetailCo', 'Peter Kim')
            ->assertSet('editDetailTr', 'Rami Lee')
            ->assertSet('editDetailCs', 'Bella Joo')
            ->assertSet('selectedInstitution.co', 'Peter Kim')
            ->assertSet('selectedInstitution.tr', 'Rami Lee')
            ->assertSet('selectedInstitution.cs', 'Bella Joo');
    }

    public function test_manager_modal_aligns_dotted_names_to_master_option_label(): void
    {
        $institution = Institution::query()->create([
            'SKcode' => 'SK-ALIGN-2',
            'AccountName' => '담당자 모달 정렬 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-ALIGN-2',
            'Account_Name' => '담당자 모달 정렬 기관',
            'CO' => 'Peter.Kim',
            'TR' => 'Rami.Lee',
            'CS' => 'Bella.Joo',
        ]);

        DB::table('employee')->insert([
            ['EMPNO' => 'E-PETER2', 'WORKDEPT' => 'A02', 'KOREANAME' => '김봉철', 'ENGLISHNAME' => 'Peter Kim', 'STATUS' => 1],
            ['EMPNO' => 'E-RAMI2', 'WORKDEPT' => 'A05', 'KOREANAME' => '이라미', 'ENGLISHNAME' => 'Rami Lee', 'STATUS' => 1],
            ['EMPNO' => 'E-BELLA2', 'WORKDEPT' => 'A03', 'KOREANAME' => '주벨라', 'ENGLISHNAME' => 'Bella Joo', 'STATUS' => 1],
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin 2',
            'email' => 'admin.align2@grapeseed.com',
            'team' => 'CO',
            'is_admin' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(InstitutionList::class)
            ->call('openManagerModal', $institution->ID)
            ->assertSet('editCo', 'Peter Kim')
            ->assertSet('editTr', 'Rami Lee')
            ->assertSet('editCs', 'Bella Joo');
    }

    public function test_detail_modal_keeps_raw_value_when_master_has_no_match(): void
    {
        // 직원 마스터에 매칭이 없으면(퇴사자/타부서 등) 원본 표기를 그대로 보여줘야 한다.
        $institution = Institution::query()->create([
            'SKcode' => 'SK-ALIGN-3',
            'AccountName' => '매칭 없음 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-ALIGN-3',
            'Account_Name' => '매칭 없음 기관',
            'CO' => 'Ghost.Person',
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin 3',
            'email' => 'admin.align3@grapeseed.com',
            'team' => 'CO',
            'is_admin' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertSet('editDetailCo', 'Ghost.Person')
            ->assertSet('selectedInstitution.co', 'Ghost.Person');
    }

    public function test_admin_in_co_team_can_see_all_institutions(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CO-DM-1',
            'AccountName' => 'DM 담당 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-CO-DM-2',
            'AccountName' => 'DM 비담당 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-CO-DM-1',
                'Account_Name' => 'DM 담당 기관',
                'CO' => 'Peter Kim',
            ],
            [
                'SK_Code' => 'SK-CO-DM-2',
                'Account_Name' => 'DM 비담당 기관',
                'CO' => 'James Kwak',
            ],
        ]);

        $admin = User::factory()->create([
            'name' => 'Peter Kim Admin',
            'email' => 'peter.admin.co@grapeseed.com',
            'team' => 'CO',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('institutions.index'))
            ->assertOk()
            ->assertSee('DM 담당 기관')
            ->assertSee('DM 비담당 기관');
    }

    public function test_list_displays_s_account_information_account_name_over_legacy_account_name(): void
    {
        $user = User::factory()->create();

        Institution::query()->create([
            'SKcode' => 'SK-NAME-PRIORITY',
            'AccountName' => '레거시 FLS 이름',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-NAME-PRIORITY',
            'Account_Name' => '마스터 기관명',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->assertSee('마스터 기관명')
            ->assertDontSee('레거시 FLS 이름');
    }

    public function test_detail_modal_prefills_edit_name_with_resolved_account_name(): void
    {
        $user = User::factory()->admin()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-DETAIL-NAME-1',
            'AccountName' => '레거시 상세 기관명',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-DETAIL-NAME-1',
            'Account_Name' => '마스터 상세 기관명',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertSet('selectedInstitution.name', '마스터 상세 기관명')
            ->assertSet('editDetailInstitutionName', '마스터 상세 기관명');
    }

    public function test_save_managers_syncs_account_name_to_account_name_and_gs_number_tables(): void
    {
        $user = User::factory()->admin()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-MANAGER-NAME-1',
            'AccountName' => '레거시 담당자 모달 기관명',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-MANAGER-NAME-1',
            'Account_Name' => '마스터 담당자 모달 기관명',
            'CO' => 'Old CO',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openManagerModal', $institution->ID)
            ->set('editCo', 'New CO')
            ->call('saveManagers')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-MANAGER-NAME-1',
            'AccountName' => '마스터 담당자 모달 기관명',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-MANAGER-NAME-1',
            'Account_Name' => '마스터 담당자 모달 기관명',
            'CO' => 'New CO',
        ]);
        $this->assertDatabaseHas('S_GSNumber', [
            'SKCode' => 'SK-MANAGER-NAME-1',
            'AccountName' => '마스터 담당자 모달 기관명',
        ]);
    }

    public function test_catalog_includes_master_only_institutions_for_total_count(): void
    {
        $user = User::factory()->create();

        Institution::query()->create([
            'SKcode' => 'SK-ONLY-MASTER',
            'AccountName' => '마스터만 있는 기관',
        ]);

        AccountInformation::query()->create([
            'SK_Code' => 'SK-WITH-INFO',
            'Account_Name' => '정보 테이블 기관',
        ]);

        $component = Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->set('statusFilter', 'all');

        $this->assertSame(2, $component->viewData('allInstitutionCount'));
        $this->assertSame(2, $component->viewData('institutions')->total());
    }

    public function test_all_status_filter_count_matches_account_information_rows(): void
    {
        $user = User::factory()->create();

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-ACTIVE',
                'Account_Name' => '운영 기관',
                'Customer_Type' => 'GTS 13 기존',
                'FGC_CreateDate' => '2024-01-11 18:18:51',
            ],
            [
                'SK_Code' => 'SK-TERM',
                'Account_Name' => '해지 기관',
                'Customer_Type' => 'GTS 16 Conversion 해지',
                'FGC_CreateDate' => '2024-01-11 18:18:52',
            ],
        ]);

        $component = Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->set('statusFilter', 'all');

        $this->assertSame(2, $component->viewData('allInstitutionCount'));
        $this->assertSame(2, $component->viewData('institutions')->total());
        $this->assertSame('전체 기관', $component->viewData('statusScopeLabel'));
    }

    public function test_active_status_filter_excludes_terminated_rows(): void
    {
        $user = User::factory()->create();

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-ACTIVE',
                'Account_Name' => '운영 기관',
                'Customer_Type' => 'GTS 13 기존',
            ],
            [
                'SK_Code' => 'SK-TERM',
                'Account_Name' => '해지 기관',
                'Customer_Type' => 'GTS 16 Conversion 해지',
            ],
        ]);

        $component = Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->set('statusFilter', 'active');

        $this->assertSame(1, $component->viewData('allInstitutionCount'));
        $this->assertSame(1, $component->viewData('institutions')->total());
        $this->assertSame('운영 기관', $component->viewData('statusScopeLabel'));
    }

    public function test_default_sort_orders_by_fgc_create_date_ascending(): void
    {
        $user = User::factory()->create();

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-NEWER',
                'Account_Name' => '나중 생성',
                'FGC_CreateDate' => '2024-01-11 18:18:56',
            ],
            [
                'SK_Code' => 'SK-OLDER',
                'Account_Name' => '먼저 생성',
                'FGC_CreateDate' => '2024-01-11 18:18:51',
            ],
        ]);

        $institutions = Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->viewData('institutions');

        $this->assertSame('SK-OLDER', $institutions->first()->SK_Code);
        $this->assertSame('SK-NEWER', $institutions->last()->SK_Code);
    }

    public function test_sorting_by_account_name_uses_account_information_name_first(): void
    {
        $user = User::factory()->create();

        Institution::query()->create([
            'SKcode' => 'SK-SORT-NAME-1',
            'AccountName' => 'Z 레거시 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-SORT-NAME-2',
            'AccountName' => 'A 레거시 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-SORT-NAME-1',
                'Account_Name' => 'A 마스터 기관',
            ],
            [
                'SK_Code' => 'SK-SORT-NAME-2',
                'Account_Name' => 'Z 마스터 기관',
            ],
        ]);

        $component = Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->set('sortField', 'AccountName')
            ->set('sortDirection', 'asc');

        $institutions = $component->viewData('institutions');
        $this->assertSame('SK-SORT-NAME-1', $institutions->first()->SK_Code);
        $this->assertSame('SK-SORT-NAME-2', $institutions->last()->SK_Code);
    }

    public function test_search_matches_s_account_information_assignees(): void
    {
        $user = User::factory()->create();

        Institution::query()->create([
            'SKcode' => 'SK-SEARCH-TR',
            'AccountName' => '검색용 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-SEARCH-TR',
            'Account_Name' => '검색용 기관',
            'TR' => 'Jeanie Park',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->set('search', 'Jeanie')
            ->assertSee('검색용 기관');
    }

    public function test_coach_team_user_only_sees_tr_assigned_institutions_from_account_information(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-TR-MINE',
            'AccountName' => '내 TR 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-TR-OTHER',
            'AccountName' => '타 TR 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-TR-MINE',
                'Account_Name' => '내 TR 기관',
                'TR' => 'Jeanie Park',
            ],
            [
                'SK_Code' => 'SK-TR-OTHER',
                'Account_Name' => '타 TR 기관',
                'TR' => 'Peter Kim',
            ],
        ]);

        $coach = User::factory()->create([
            'name' => 'Jeanie Park',
            'email' => 'jeanie.park@grapeseed.com',
            'team' => 'TR',
            'is_admin' => false,
        ]);

        Livewire::actingAs($coach)
            ->test(InstitutionList::class)
            ->assertSee('내 TR 기관')
            ->assertDontSee('타 TR 기관');
    }

    public function test_user_can_filter_only_my_assigned_institutions(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-MY-1',
            'AccountName' => '내 담당 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-MY-2',
            'AccountName' => '타 담당 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-MY-1',
                'Account_Name' => '내 담당 기관',
                'CO' => 'Peter Kim',
            ],
            [
                'SK_Code' => 'SK-MY-2',
                'Account_Name' => '타 담당 기관',
                'CO' => 'James Kwak',
            ],
        ]);

        $user = User::factory()->create([
            'name' => 'Peter Kim',
            'email' => 'peter.kim@grapeseed.com',
            'team' => null,
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->set('assignmentFilter', 'my_assigned')
            ->assertSee('내 담당 기관')
            ->assertDontSee('타 담당 기관');
    }

    public function test_detail_modal_prefers_gs_number_from_s_gs_number_table(): void
    {
        $user = User::factory()->create();

        Institution::query()->create([
            'SKcode' => 'SK-GS-LIST-1',
            'AccountName' => 'GS 표시 기관',
            'GSno' => '9.99',
        ]);

        GsNumber::query()->create([
            'SKCode' => 'SK-GS-LIST-1',
            'AccountName' => 'GS 표시 기관',
            'GSnumber' => '1.14',
        ]);

        $acct = AccountInformation::query()->create([
            'SK_Code' => 'SK-GS-LIST-1',
            'Account_Name' => 'GS 표시 기관',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $acct->ID)
            ->assertSee('1.14')
            ->assertDontSee('9.99');
    }

    public function test_save_detail_syncs_s_gs_number(): void
    {
        $user = User::factory()->admin()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-GS-SAVE-1',
            'AccountName' => '저장 테스트 기관',
            'GSno' => '1',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-GS-SAVE-1',
            'Account_Name' => '저장 테스트 기관',
            'CO' => 'CO One',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editGsNo', '2.5')
            ->call('saveDetailFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_GSNumber', [
            'SKCode' => 'SK-GS-SAVE-1',
            'GSnumber' => '2.5',
        ]);

        $this->assertDatabaseHas('S_AccountName', [
            'ID' => $institution->ID,
            'GSno' => '2.5',
        ]);
    }

    public function test_save_detail_updates_master_and_account_information(): void
    {
        $user = User::factory()->admin()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-FULL-1',
            'AccountName' => 'Old Name',
            'EnglishName' => 'Old En',
            'Director' => 'Dir',
            'Address' => 'Old Addr',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-FULL-1',
            'Account_Name' => 'Old Name',
            'CO' => 'C',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editDetailInstitutionName', 'New Name')
            ->set('editDetailEnglishName', 'New En')
            ->set('editDetailDirector', 'New Dir')
            ->set('editDetailAddress', 'Addr 1')
            ->call('saveDetailFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_AccountName', [
            'ID' => $institution->ID,
            'AccountName' => 'New Name',
            'EnglishName' => 'New En',
            'Director' => 'New Dir',
            'Address' => 'Addr 1',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-FULL-1',
            'Account_Name' => 'New Name',
            'Address' => 'Addr 1',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_save_detail_fields_reverse_syncs_completed_sk_code_request(): void
    {
        $user = User::factory()->admin()->create();
        $appliedAt = now()->subMinutes(5);

        $institution = Institution::query()->create([
            'SKcode' => 'SK-REVERSE-1',
            'AccountName' => '이전 기관명',
            'PortalCampusID' => 'OLD-CAMPUS',
            'AccountNo' => 'OLD-ACCOUNT',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-REVERSE-1',
            'Account_Name' => '이전 기관명',
            'CO' => 'Old CO',
            'TR' => 'Old TR',
            'CS' => 'Old CS',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 1001,
            'institution_name' => '이전 기관명',
            'temp_sk_code' => 'LEAD-1001',
            'final_sk_code' => 'SK-REVERSE-1',
            'portal_campus_id' => 'OLD-CAMPUS',
            'account_no' => 'OLD-ACCOUNT',
            'co' => 'Old CO',
            'tr' => 'Old TR',
            'cs' => 'Old CS',
            'status' => 'completed',
            'requested_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
            'applied_at' => $appliedAt,
            'created_at' => $appliedAt,
            'updated_at' => $appliedAt,
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editDetailInstitutionName', '우리쪽 수정 기관명')
            ->set('editDetailPortalCampusId', 'LOCAL-CAMPUS')
            ->set('editDetailAccountNo', 'LOCAL-ACCOUNT')
            ->set('editDetailCo', 'Local CO')
            ->set('editDetailTr', 'Local TR')
            ->set('editDetailCs', 'Local CS')
            ->call('saveDetailFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sk_code_requests', [
            'final_sk_code' => 'SK-REVERSE-1',
            'institution_name' => '우리쪽 수정 기관명',
            'portal_campus_id' => 'LOCAL-CAMPUS',
            'account_no' => 'LOCAL-ACCOUNT',
            'co' => 'Local CO',
            'tr' => 'Local TR',
            'cs' => 'Local CS',
        ]);

        $request = DB::table('sk_code_requests')->where('final_sk_code', 'SK-REVERSE-1')->first();
        $this->assertNotNull($request->applied_at);
        $this->assertTrue($request->applied_at >= $request->updated_at);
    }

    public function test_save_managers_reverse_syncs_completed_sk_code_request(): void
    {
        $user = User::factory()->admin()->create();
        $appliedAt = now()->subMinutes(5);

        $institution = Institution::query()->create([
            'SKcode' => 'SK-REVERSE-MANAGER-1',
            'AccountName' => '담당자 역동기화 기관',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 1002,
            'institution_name' => '담당자 역동기화 기관',
            'temp_sk_code' => 'LEAD-1002',
            'final_sk_code' => 'SK-REVERSE-MANAGER-1',
            'portal_campus_id' => null,
            'account_no' => null,
            'co' => 'Old CO',
            'tr' => 'Old TR',
            'cs' => 'Old CS',
            'status' => 'completed',
            'requested_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
            'applied_at' => $appliedAt,
            'created_at' => $appliedAt,
            'updated_at' => $appliedAt,
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openManagerModal', $institution->ID)
            ->set('editCo', 'New CO')
            ->set('editTr', 'New TR')
            ->set('editCs', 'New CS')
            ->call('saveManagers')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sk_code_requests', [
            'final_sk_code' => 'SK-REVERSE-MANAGER-1',
            'institution_name' => '담당자 역동기화 기관',
            'co' => 'New CO',
            'tr' => 'New TR',
            'cs' => 'New CS',
        ]);
    }

    public function test_save_managers_queues_outbound_when_enabled(): void
    {
        Config::set('services.institution_outbound.enabled', true);
        $user = User::factory()->admin()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-MANAGER-SYNC-1',
            'AccountName' => '담당자 동기화 테스트',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openManagerModal', $institution->ID)
            ->set('editCo', 'CO Sync')
            ->set('editTr', 'TR Sync')
            ->set('editCs', 'CS Sync')
            ->call('saveManagers')
            ->assertHasNoErrors();

        Queue::assertPushed(SyncInstitutionOutboundJob::class, function (SyncInstitutionOutboundJob $job): bool {
            return $job->sk === 'SK-MANAGER-SYNC-1';
        });
    }

    public function test_save_managers_creates_local_assignment_change_request_with_origin_a(): void
    {
        Config::set('services.assignment_sync.enabled', true);
        $user = User::factory()->admin()->create(['name' => 'Manager Owner']);

        $institution = Institution::query()->create([
            'SKcode' => 'SK-ASSIGN-A-1',
            'AccountName' => '담당자 변경 A 테스트',
        ]);

        AccountInformation::query()->create([
            'SK_Code' => 'SK-ASSIGN-A-1',
            'Account_Name' => '담당자 변경 A 테스트',
            'CO' => 'Old CO',
            'TR' => 'Old TR',
            'CS' => 'Old CS',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openManagerModal', $institution->ID)
            ->set('editCo', 'New CO')
            ->set('editTr', 'Old TR')
            ->set('editCs', 'Old CS')
            ->call('saveManagers')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('assignment_change_requests', [
            'sk_code' => 'SK-ASSIGN-A-1',
            'origin' => AssignmentChangeRequest::ORIGIN_LOCAL,
            'status' => AssignmentChangeRequest::STATUS_PENDING,
            'co' => 'New CO',
            'tr' => null,
            'cs' => null,
            'changed_by' => 'Manager Owner',
        ]);
    }

    public function test_process_assignment_change_requests_job_applies_k_origin_to_account_information(): void
    {
        Config::set('services.assignment_sync.enabled', true);

        Institution::query()->create([
            'SKcode' => 'SK-ASSIGN-K-1',
            'AccountName' => '담당자 변경 K 테스트',
        ]);

        AccountInformation::query()->create([
            'SK_Code' => 'SK-ASSIGN-K-1',
            'Account_Name' => '담당자 변경 K 테스트',
            'CO' => 'Old CO',
            'TR' => 'Old TR',
            'CS' => 'Old CS',
        ]);

        AssignmentChangeRequest::query()->create([
            'sk_code' => 'SK-ASSIGN-K-1',
            'co' => 'New CO',
            'tr' => 'Old TR',
            'cs' => 'Old CS',
            'origin' => AssignmentChangeRequest::ORIGIN_EXTERNAL,
            'status' => AssignmentChangeRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        (new ProcessAssignmentChangeRequestsJob)->handle();

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-ASSIGN-K-1',
            'CO' => 'New CO',
            'TR' => 'Old TR',
            'CS' => 'Old CS',
        ]);

        $this->assertDatabaseHas('assignment_change_requests', [
            'sk_code' => 'SK-ASSIGN-K-1',
            'origin' => AssignmentChangeRequest::ORIGIN_EXTERNAL,
            'status' => AssignmentChangeRequest::STATUS_APPLIED,
        ]);

        $this->assertDatabaseHas('external_assignment_inbound_logs', [
            'sk_code' => 'SK-ASSIGN-K-1',
            'co' => 'New CO',
            'status' => 'applied',
        ]);
    }

    public function test_detail_modal_shows_latest_manager_changed_at_by_role(): void
    {
        $user = User::factory()->admin()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-ASSIGN-DATE-1',
            'AccountName' => '변경일 표시 테스트',
        ]);

        AccountInformation::query()->create([
            'SK_Code' => 'SK-ASSIGN-DATE-1',
            'Account_Name' => '변경일 표시 테스트',
            'CO' => 'Current CO',
            'TR' => 'Current TR',
            'CS' => 'Current CS',
        ]);

        AssignmentChangeRequest::query()->create([
            'sk_code' => 'SK-ASSIGN-DATE-1',
            'co' => 'Older CO',
            'changed_by' => 'Old Owner',
            'origin' => AssignmentChangeRequest::ORIGIN_LOCAL,
            'status' => AssignmentChangeRequest::STATUS_APPLIED,
            'requested_at' => '2026-01-05 10:00:00',
            'applied_at' => '2026-01-05 11:00:00',
        ]);
        AssignmentChangeRequest::query()->create([
            'sk_code' => 'SK-ASSIGN-DATE-1',
            'co' => 'Current CO',
            'changed_by' => 'CO Owner',
            'origin' => AssignmentChangeRequest::ORIGIN_LOCAL,
            'status' => AssignmentChangeRequest::STATUS_APPLIED,
            'requested_at' => '2026-03-01 10:00:00',
            'applied_at' => '2026-03-01 11:00:00',
        ]);
        AssignmentChangeRequest::query()->create([
            'sk_code' => 'SK-ASSIGN-DATE-1',
            'tr' => 'Current TR',
            'changed_by' => 'TR Owner',
            'origin' => AssignmentChangeRequest::ORIGIN_LOCAL,
            'status' => AssignmentChangeRequest::STATUS_APPLIED,
            'requested_at' => '2026-02-10 10:00:00',
            'applied_at' => '2026-02-10 11:00:00',
        ]);
        AssignmentChangeRequest::query()->create([
            'sk_code' => 'SK-ASSIGN-DATE-1',
            'cs' => 'Current CS',
            'changed_by' => 'CS Owner',
            'origin' => AssignmentChangeRequest::ORIGIN_LOCAL,
            'status' => AssignmentChangeRequest::STATUS_APPLIED,
            'requested_at' => '2026-02-20 10:00:00',
            'applied_at' => '2026-02-20 11:00:00',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertSet('selectedInstitution.co_changed_at', '2026-03-01')
            ->assertSet('selectedInstitution.tr_changed_at', '2026-02-10')
            ->assertSet('selectedInstitution.cs_changed_at', '2026-02-20')
            ->assertSet('selectedInstitution.co_changed_by', 'CO Owner')
            ->assertSet('selectedInstitution.tr_changed_by', 'TR Owner')
            ->assertSet('selectedInstitution.cs_changed_by', 'CS Owner')
            ->assertSee('최근 변경')
            ->assertSee('2026-03-01')
            ->assertSee('CO Owner')
            ->assertSee('2026-02-10')
            ->assertSee('TR Owner')
            ->assertSee('2026-02-20')
            ->assertSee('CS Owner');
    }

    public function test_detail_modal_falls_back_to_requested_at_and_shows_dash_for_missing_role(): void
    {
        $user = User::factory()->admin()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-ASSIGN-DATE-2',
            'AccountName' => '변경일 폴백 테스트',
        ]);

        AccountInformation::query()->create([
            'SK_Code' => 'SK-ASSIGN-DATE-2',
            'Account_Name' => '변경일 폴백 테스트',
            'TR' => 'Current TR',
        ]);

        AssignmentChangeRequest::query()->create([
            'sk_code' => 'SK-ASSIGN-DATE-2',
            'tr' => 'Current TR',
            'changed_by' => null,
            'origin' => AssignmentChangeRequest::ORIGIN_LOCAL,
            'status' => AssignmentChangeRequest::STATUS_PENDING,
            'requested_at' => '2026-04-11 09:00:00',
            'applied_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->assertSet('selectedInstitution.co_changed_at', null)
            ->assertSet('selectedInstitution.tr_changed_at', '2026-04-11')
            ->assertSet('selectedInstitution.cs_changed_at', null)
            ->assertSet('selectedInstitution.tr_changed_by', 'Internal Update')
            ->assertSee('2026-04-11')
            ->assertSee('Internal Update');
    }

    public function test_save_detail_fields_queues_outbound_when_enabled(): void
    {
        Config::set('services.institution_outbound.enabled', true);
        $user = User::factory()->admin()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-DETAIL-SYNC-1',
            'AccountName' => '상세 동기화 테스트',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editDetailCo', 'CO Detail Sync')
            ->set('editDetailTr', 'TR Detail Sync')
            ->set('editDetailCs', 'CS Detail Sync')
            ->call('saveDetailFields')
            ->assertHasNoErrors();

        Queue::assertPushed(SyncInstitutionOutboundJob::class, function (SyncInstitutionOutboundJob $job): bool {
            return $job->sk === 'SK-DETAIL-SYNC-1';
        });
    }

    public function test_save_detail_sk_rename_cascades_sk_code_on_support_records(): void
    {
        $user = User::factory()->admin()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-OLD-X',
            'AccountName' => 'SK 변경 테스트',
        ]);

        DB::table('S_SupportInfo_Account')->insert([
            'SK_Code' => 'SK-OLD-X',
            'Year' => 2025,
            'Support_Date' => '2025-01-15',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editDetailSkCode', 'SK-NEW-X')
            ->call('saveDetailFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_AccountName', [
            'ID' => $institution->ID,
            'SKcode' => 'SK-NEW-X',
        ]);
        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-NEW-X',
        ]);
        $this->assertDatabaseMissing('S_SupportInfo_Account', [
            'SK_Code' => 'SK-OLD-X',
        ]);
    }

    /**
     * 담당자 드롭다운 옵션이
     *   - 부서 매핑(A02=CO, A05=Coach, A03=CS) 기준으로
     *   - STATUS=1 활성 직원만
     *   - employee 마스터에 존재하는 이름만
     * 포함되는지 검증합니다.
     */
    public function test_manager_dropdown_options_only_show_active_employees_in_mapped_department(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // 활성: 각 부서에 1명씩 정상 직원
        $this->insertEmployee('E001', 'A02', 'Peter Kim', 1);     // CO 후보
        $this->insertEmployee('E002', 'A05', 'Coach Hong', 1);     // Coach 후보
        $this->insertEmployee('E003', 'A03', 'CS Choi', 1);        // CS 후보

        // 비활성: 옵션에서 제외되어야 함
        $this->insertEmployee('E004', 'A02', 'Inactive Co', 0);
        $this->insertEmployee('E005', 'A05', 'Inactive Coach', 0);
        $this->insertEmployee('E006', 'A03', 'Inactive Cs', 0);

        // 다른 부서: 옵션에서 제외되어야 함
        $this->insertEmployee('E007', 'A01', 'Admin Lee', 1);

        // 과거 S_Account_Information 이력에만 남아있는 비직원 이름.
        // 변경 전이면 드롭다운에 떴겠지만, 이제 employee 기준이므로 빠져야 합니다.
        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-LEGACY-1',
            'CO' => 'Ghost Co',
            'TR' => 'Ghost Tr',
            'CS' => 'Ghost Cs',
        ]);

        $component = Livewire::actingAs($admin)->test(InstitutionList::class);

        $coOptions = $component->viewData('coManagerOptions')->all();
        $trOptions = $component->viewData('trManagerOptions')->all();
        $csOptions = $component->viewData('csManagerOptions')->all();

        $this->assertSame(['Peter Kim'], $coOptions);
        $this->assertSame(['Coach Hong'], $trOptions);
        $this->assertSame(['CS Choi'], $csOptions);

        foreach (['Ghost Co', 'Ghost Tr', 'Ghost Cs', 'Inactive Co', 'Inactive Coach', 'Inactive Cs', 'Admin Lee'] as $excluded) {
            $this->assertNotContains($excluded, $coOptions);
            $this->assertNotContains($excluded, $trOptions);
            $this->assertNotContains($excluded, $csOptions);
        }
    }

    /**
     * ENGLISHNAME 이 비어 있는 경우 KOREANAME 으로 폴백되는지 확인합니다.
     */
    public function test_manager_dropdown_falls_back_to_korean_name_when_english_is_blank(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        DB::table('employee')->insert([
            'EMPNO' => 'E100',
            'WORKDEPT' => 'A02',
            'KOREANAME' => '김기획',
            'ENGLISHNAME' => '',
            'STATUS' => 1,
        ]);

        $coOptions = Livewire::actingAs($admin)
            ->test(InstitutionList::class)
            ->viewData('coManagerOptions')
            ->all();

        $this->assertSame(['김기획'], $coOptions);
    }

    private function insertEmployee(string $empno, string $workdept, string $englishName, int $status): void
    {
        DB::table('employee')->insert([
            'EMPNO' => $empno,
            'WORKDEPT' => $workdept,
            'KOREANAME' => $englishName,
            'ENGLISHNAME' => $englishName,
            'STATUS' => $status,
        ]);
    }
}
