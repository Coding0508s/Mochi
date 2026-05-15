<?php

namespace Tests\Feature;

use App\Jobs\SyncInstitutionOutboundJob;
use App\Livewire\InstitutionList;
use App\Models\GsNumber;
use App\Models\Institution;
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
            $table->string('SK_Code', 100)->nullable();
            $table->dateTime('Support_Date')->nullable();
            $table->integer('Year')->nullable();
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

    public function test_list_prefers_gs_number_from_s_gs_number_table(): void
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

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->assertSee('1.14')
            ->assertDontSee('9.99');
    }

    public function test_save_detail_syncs_s_gs_number(): void
    {
        $user = User::factory()->create();

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
        $user = User::factory()->create();

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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();

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

    public function test_save_detail_fields_queues_outbound_when_enabled(): void
    {
        Config::set('services.institution_outbound.enabled', true);
        $user = User::factory()->create();

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
        $user = User::factory()->create();

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
