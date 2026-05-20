<?php

namespace Tests\Feature;

use App\Enums\SyncOrigin;
use App\Jobs\SyncInstitutionOutboundJob;
use App\Livewire\PotentialInstitutionList;
use App\Mail\PotentialMeetingStoredMail;
use App\Models\AccountInformation;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use App\Services\PotentialInstitutionSkCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class PotentialInstitutionListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.institution_outbound.enabled', false);
        $this->createLegacyPotentialInstitutionTables();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    /**
     * PHPUnit 환경은 sqlite :memory: 이며 레거시 테이블 마이그레이션이 없으므로 최소 스키마만 생성합니다.
     */
    private function createLegacyPotentialInstitutionTables(): void
    {
        Schema::dropIfExists('S_CO_NewTarget_Detail');
        Schema::dropIfExists('S_CO_NewTarget');
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');
        Schema::dropIfExists('employee');
        Schema::dropIfExists('institution_visibility_overrides');

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
            $table->unsignedInteger('LS')->default(0);
            $table->unsignedInteger('GS_K')->default(0);
            $table->unsignedInteger('GS_E')->default(0);
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

        Schema::create('employee', function (Blueprint $table): void {
            $table->string('EMPNO')->primary();
            $table->string('WORKDEPT')->nullable();
            $table->string('KOREANAME')->nullable();
            $table->string('ENGLISHNAME')->nullable();
            $table->string('EMAIL')->nullable();
            $table->unsignedTinyInteger('STATUS')->default(1);
        });

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->unsignedInteger('potential_target_id')->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->date('Support_Date')->nullable();
            $table->string('Meet_Time', 50)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->string('Target', 255)->nullable();
            $table->text('Issue')->nullable();
            $table->text('TO_Account')->nullable();
            $table->text('TO_Depart')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
            $table->timestamp('CreatedDate')->nullable();
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
            $table->string('Type', 100);
            $table->string('Gubun', 100);
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

        Schema::create('institution_visibility_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('sk_code', 100)->unique();
            $table->string('hidden_reason', 100)->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_open_create_modal_prefills_manager_from_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => '로그인담당자']);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->assertSet('newManager', '로그인담당자');
    }

    public function test_save_new_target_validates_required_fields(): void
    {
        Livewire::test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', '')
            ->set('newMeetingDate', '')
            ->call('saveNewTarget')
            ->assertHasErrors([
                'newAccountName',
                'newMeetingDate',
            ]);
    }

    public function test_save_new_target_does_not_issue_sk_before_contract(): void
    {
        $user = User::factory()->create();
        $accountName = 'SK코드 생략 QA '.uniqid('', true);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', $accountName)
            ->set('newType', '신규(25년)')
            ->set('newConsultingType', '신규기관방문')
            ->set('newMeetingDate', '2026-04-06')
            ->call('saveNewTarget')
            ->assertSet('showCreateModal', false)
            ->assertHasNoErrors();

        $target = CoNewTarget::query()->where('AccountName', $accountName)->first();
        $this->assertNotNull($target);
        $this->assertNull($target->AccountCode);
        $this->assertSame($user->id, (int) $target->created_by);
        $this->assertDatabaseMissing('S_AccountName', [
            'AccountName' => $accountName,
        ]);
        $this->assertDatabaseMissing('S_Account_Information', [
            'Account_Name' => $accountName,
        ]);
    }

    public function test_save_new_target_allows_first_meeting_when_manager_differs_from_creator(): void
    {
        $user = User::factory()->create(['name' => '로그인 사용자']);
        $accountName = '담당자 다른 신규 잠재기관 '.uniqid('', true);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', $accountName)
            ->set('newType', '신규(25년)')
            ->set('newConsultingType', '신규기관방문')
            ->set('newMeetingDate', '2026-04-06')
            ->set('newManager', '다른 담당자')
            ->call('saveNewTarget')
            ->assertSet('showCreateModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_CO_NewTarget', [
            'AccountName' => $accountName,
            'AccountManager' => '다른 담당자',
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'AccountName' => $accountName,
            'AccountManager' => '다른 담당자',
        ]);
    }

    public function test_save_new_target_persists_target_and_first_meeting_detail(): void
    {
        $accountName = 'QA 테스트 기관 '.uniqid('', true);
        Livewire::test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', $accountName)
            ->set('newType', '신규(25년)')
            ->set('newConsultingType', '신규기관방문')
            ->set('newMeetingDate', '2026-04-06')
            ->set('newMeetingTime', '13:00')
            ->set('newMeetingTimeEnd', '')
            ->set('newManager', 'James Kwak')
            ->set('newDescription', '첫 미팅 메모')
            ->set('newLS', '2')
            ->set('newGSK', '3')
            ->set('newGSE', '1')
            ->call('saveNewTarget')
            ->assertSet('showCreateModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_CO_NewTarget', [
            'AccountName' => $accountName,
            'AccountCode' => null,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 2,
            'GS_K' => 3,
            'GS_E' => 1,
            'Total' => 6,
        ]);

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'AccountName' => $accountName,
            'AccountManager' => 'James Kwak',
            'ConsultingType' => '신규기관방문',
        ]);

        $detail = CoNewTargetDetail::query()->where('AccountName', $accountName)->first();
        $this->assertNotNull($detail);
        $this->assertStringContainsString('첫 미팅', (string) $detail->Description);

        $this->assertDatabaseMissing('S_AccountName', [
            'AccountName' => $accountName,
        ]);
        $this->assertDatabaseMissing('S_Account_Information', [
            'Account_Name' => $accountName,
        ]);
    }

    public function test_save_new_target_meeting_mail_includes_student_counts(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['group@test.org'],
        ]);

        $accountName = 'QA 미팅 메일 인원 '.uniqid('', true);

        Livewire::test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', $accountName)
            ->set('newType', '신규(25년)')
            ->set('newConsultingType', '신규기관방문')
            ->set('newMeetingDate', '2026-04-06')
            ->set('newMeetingTime', '13:00')
            ->set('newMeetingTimeEnd', '13:30')
            ->set('newManager', 'James Kwak')
            ->set('newDescription', '첫 미팅 메모')
            ->set('newLS', '2')
            ->set('newGSK', '3')
            ->set('newGSE', '1')
            ->call('saveNewTarget')
            ->assertSet('showCreateModal', false)
            ->assertHasNoErrors();

        Mail::assertSent(PotentialMeetingStoredMail::class, function (PotentialMeetingStoredMail $mail) use ($accountName): bool {
            return $mail->hasTo('group@test.org')
                && $mail->meetingDetail->AccountName === $accountName
                && $mail->meetingDetail->Description === '첫 미팅 메모'
                && $mail->studentCounts === [
                    'ls' => 2,
                    'gs_k' => 3,
                    'gs_e' => 1,
                    'total' => 6,
                ];
        });
    }

    public function test_save_new_target_with_support_report_creates_support_record(): void
    {
        config(['potential_institutions.show_support_report_ui' => true]);

        $user = User::factory()->create(['name' => '보고서작성자']);
        $accountName = 'QA 지원보고서 동시등록 '.uniqid('', true);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', $accountName)
            ->set('newType', '신규(25년)')
            ->set('newConsultingType', '신규기관방문')
            ->set('newMeetingDate', '2026-04-10')
            ->set('newMeetingTime', '10:00')
            ->set('newIncludeSupportReport', true)
            ->set('newSupportReportDate', '2026-04-10')
            ->set('newSupportReportTime', '11:30')
            ->set('newSupportReportType', '대면')
            ->set('newSupportReportTarget', '원장')
            ->set('newSupportReportToAccount', '소통 메모')
            ->set('newSupportReportCompleted', true)
            ->call('saveNewTarget')
            ->assertSet('showCreateModal', false)
            ->assertHasNoErrors();

        $target = CoNewTarget::query()->where('AccountName', $accountName)->first();
        $this->assertNotNull($target);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'potential_target_id' => $target->ID,
            'Account_Name' => $accountName,
            'Support_Type' => '대면',
            'Target' => '원장',
            'Status' => '완료',
        ]);

        $record = SupportRecord::query()
            ->where('potential_target_id', $target->ID)
            ->first();
        $this->assertNotNull($record);
        $this->assertNull($record->SK_Code);
        $this->assertNotNull($record->CompletedDate);
    }

    public function test_save_new_target_with_support_report_requires_support_fields(): void
    {
        config(['potential_institutions.show_support_report_ui' => true]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', '검증 실패 케이스')
            ->set('newType', '신규(25년)')
            ->set('newConsultingType', '신규기관방문')
            ->set('newMeetingDate', '2026-04-06')
            ->set('newIncludeSupportReport', true)
            ->set('newSupportReportDate', '')
            ->set('newSupportReportTime', '')
            ->set('newSupportReportType', '')
            ->call('saveNewTarget')
            ->assertHasErrors([
                'newSupportReportDate',
                'newSupportReportTime',
                'newSupportReportType',
            ]);
    }

    public function test_open_detail_modal_includes_support_records_linked_by_potential_target_id(): void
    {
        $accountName = 'QA SK없음 지원내역 '.uniqid('', true);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-06',
            'AccountManager' => 'Mgr',
            'AccountCode' => null,
            'AccountName' => $accountName,
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => null,
            'created_by' => auth()->id(),
        ]);

        SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => null,
            'potential_target_id' => $target->ID,
            'Account_Name' => $accountName,
            'TR_Name' => 'TR',
            'Support_Date' => '2026-04-07',
            'Meet_Time' => '09:15:00',
            'Support_Type' => '전화',
            'Target' => null,
            'Issue' => null,
            'TO_Account' => null,
            'TO_Depart' => null,
            'Status' => '진행중',
            'CompletedDate' => null,
            'CreatedDate' => now(),
        ]);

        $component = Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', (int) $target->ID);

        $rows = $component->get('detailSupportRecords');
        $this->assertCount(1, $rows);
        $this->assertSame('전화', $rows[0]['support_type'] ?? null);
        $this->assertSame('2026-04-07', $rows[0]['support_date'] ?? null);
    }

    public function test_save_new_target_works_even_if_same_name_exists_in_institution_list(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-DUP-TEST',
            'AccountName' => '신규 이름',
            'Director' => null,
            'Phone' => null,
            'Address' => null,
            'Gubun' => null,
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', '신규 이름')
            ->set('newType', '신규(25년)')
            ->set('newConsultingType', '신규기관방문')
            ->set('newMeetingDate', '2026-04-06')
            ->call('saveNewTarget')
            ->assertHasNoErrors();
    }

    public function test_open_detail_modal_shows_created_meeting_history(): void
    {
        $accountName = 'QA 상세모달 '.uniqid('', true);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-06',
            'AccountManager' => 'Peter Kim',
            'AccountCode' => 'SK-DETAIL-'.uniqid(),
            'AccountName' => $accountName,
            'Address' => '테스트 주소',
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 1,
            'GS_K' => 2,
            'GS_E' => 0,
            'Total' => 3,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => $accountName,
            'AccountManager' => 'Peter Kim',
            'MeetingDate' => '2026-04-06 00:00:00',
            'MeetingTime' => '10:30',
            'MeetingTime_End' => null,
            'Description' => '상세에서 볼 미팅 본문',
            'ConsultingType' => '신규기관방문',
            'Possibility' => 'A',
        ]);

        $id = (int) CoNewTarget::query()->where('AccountName', $accountName)->value('ID');

        $component = Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', $id);

        $this->assertTrue($component->get('showDetailModal'));
        $selected = $component->get('selectedTarget');
        $this->assertIsArray($selected);
        $this->assertSame($accountName, $selected['account_name'] ?? null);

        $meetings = $component->get('detailMeetings');
        $this->assertCount(1, $meetings);
        $this->assertSame('상세에서 볼 미팅 본문', $meetings[0]['description'] ?? null);
    }

    public function test_apply_external_sk_from_api_renames_institution_keys(): void
    {
        $accountName = 'API SK 반영 QA '.uniqid('', true);
        $lead = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-06',
            'AccountManager' => null,
            'AccountCode' => 'x',
            'AccountName' => $accountName,
            'Address' => '주소',
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => null,
            'created_by' => auth()->id(),
        ]);

        $leadSk = 'LEAD-'.$lead->ID;
        $lead->update(['AccountCode' => $leadSk]);

        Institution::query()->create([
            'SKcode' => $leadSk,
            'AccountName' => $accountName,
            'Director' => null,
            'Phone' => null,
            'Address' => '주소',
            'Gubun' => null,
        ]);

        AccountInformation::query()->create([
            'SK_Code' => $leadSk,
            'Account_Name' => $accountName,
            'Address' => '주소',
        ]);

        $apiSk = 'SK-API-'.uniqid();
        app(PotentialInstitutionSkCodeService::class)->applyExternalSk($lead->fresh(), $apiSk);

        $this->assertDatabaseHas('S_CO_NewTarget', [
            'ID' => $lead->ID,
            'AccountCode' => $apiSk,
        ]);
        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $apiSk,
            'AccountName' => $accountName,
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $apiSk,
        ]);
        $this->assertDatabaseMissing('S_AccountName', ['SKcode' => $leadSk]);
    }

    public function test_mark_contract_complete_sets_contract_flags(): void
    {
        Queue::fake();
        $name = '계약완료 QA '.uniqid('', true);
        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => 'Peter Kim',
            'AccountName' => $name,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'B',
            'created_by' => auth()->id(),
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $row->refresh();
        $this->assertTrue((bool) $row->IsContract);
        $this->assertNotNull($row->ContractedDate);
        $this->assertSame('LEAD-'.$row->ID, trim((string) $row->AccountCode));
        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'LEAD-'.$row->ID,
            'AccountName' => $name,
            'Possibility' => 'B',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'LEAD-'.$row->ID,
            'Account_Name' => $name,
            'CO' => 'Peter Kim',
        ]);
        Queue::assertNothingPushed();

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $row->refresh();
        $this->assertTrue((bool) $row->IsContract);
        $this->assertSame(1, Institution::query()->where('SKcode', 'LEAD-'.$row->ID)->count());
    }

    public function test_mark_contract_complete_does_not_duplicate_institution_when_sk_already_in_list(): void
    {
        Queue::fake();
        $name = '기존SK계약 QA '.uniqid('', true);
        $existingSk = 'SK-EXIST-'.uniqid('', true);
        Institution::query()->create([
            'SKcode' => $existingSk,
            'AccountName' => $name,
            'Director' => null,
            'Phone' => null,
            'Address' => null,
            'Gubun' => null,
        ]);

        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountCode' => $existingSk,
            'AccountName' => $name,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'B',
            'created_by' => auth()->id(),
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $this->assertSame(1, Institution::query()->where('SKcode', $existingSk)->count());
    }

    public function test_non_creator_cannot_mark_contract_complete(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => '타인 등록 잠재기관 '.uniqid('', true),
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'B',
            'created_by' => $owner->id,
        ]);

        Livewire::actingAs($other)
            ->test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID)
            ->assertHasErrors(['authorization']);

        $row->refresh();
        $this->assertFalse((bool) $row->IsContract);
        Queue::assertNothingPushed();
    }

    public function test_legacy_target_manager_can_mark_contract_complete_without_creator(): void
    {
        Queue::fake();
        $user = User::factory()->create(['name' => '기존 담당자']);
        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => '기존 담당자',
            'AccountName' => '기존 데이터 잠재기관 '.uniqid('', true),
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'B',
            'created_by' => null,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID)
            ->assertHasNoErrors();

        $row->refresh();
        $this->assertTrue((bool) $row->IsContract);
    }

    public function test_recontract_preserves_existing_master_row_and_clears_termination(): void
    {
        Queue::fake();

        $existingSk = 'SK-RECONTRACT-'.uniqid('', true);
        Institution::query()->create([
            'SKcode' => $existingSk,
            'AccountName' => '기존 기관명',
            'Director' => '기존 원장',
            'Phone' => '02-1111-1111',
            'Address' => '기존 주소',
            'Gubun' => '기존 구분',
            'Possibility' => 'A',
        ]);

        AccountInformation::query()->create([
            'SK_Code' => $existingSk,
            'Account_Name' => '기존 기관명',
            'Customer_Type' => 'VIP 해지',
            'Address' => '기존 담당 주소',
        ]);

        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountCode' => $existingSk,
            'AccountName' => '재계약 잠재명',
            'Director' => '새 원장',
            'Phone' => '02-2222-2222',
            'Address' => '새 주소',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'B',
            'created_by' => auth()->id(),
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $existingSk,
            'AccountName' => '기존 기관명',
            'Director' => '기존 원장',
            'Phone' => '02-1111-1111',
            'Address' => '기존 주소',
            'Gubun' => '기존 구분',
            'Possibility' => 'A',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $existingSk,
            'Account_Name' => '기존 기관명',
            'Customer_Type' => 'VIP',
            'Address' => '기존 담당 주소',
        ]);
        $this->assertSame(1, Institution::query()->where('SKcode', $existingSk)->count());
        $this->assertSame(1, AccountInformation::query()->where('SK_Code', $existingSk)->count());
    }

    public function test_mark_contract_complete_clears_hidden_institution_override(): void
    {
        Queue::fake();
        $name = '숨김해제 계약 QA '.uniqid('', true);
        $existingSk = 'SK-HIDDEN-'.uniqid('', true);

        Institution::query()->create([
            'SKcode' => $existingSk,
            'AccountName' => $name,
            'Director' => null,
            'Phone' => null,
            'Address' => null,
            'Gubun' => null,
        ]);

        DB::table('institution_visibility_overrides')->insert([
            'sk_code' => $existingSk,
            'hidden_reason' => 'uncontracted',
            'hidden_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountCode' => $existingSk,
            'AccountName' => $name,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'B',
            'created_by' => auth()->id(),
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $this->assertDatabaseMissing('institution_visibility_overrides', [
            'sk_code' => $existingSk,
        ]);
        $this->assertSame(1, Institution::query()->where('SKcode', $existingSk)->count());
    }

    public function test_mark_contract_complete_queues_outbound_when_enabled(): void
    {
        Queue::fake();
        Config::set('services.institution_outbound.enabled', true);

        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => 'Outbound 계약 QA '.uniqid('', true),
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => null,
            'created_by' => auth()->id(),
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $row->refresh();
        Queue::assertPushed(SyncInstitutionOutboundJob::class, function (SyncInstitutionOutboundJob $job) use ($row): bool {
            return $job->sk === 'LEAD-'.$row->ID;
        });
    }

    public function test_outbound_job_sends_master_and_assignment_payload(): void
    {
        Config::set('services.institution_outbound.enabled', true);
        Config::set('services.institution_outbound.base_url', 'https://partner.example');
        Config::set('services.institution_outbound.bearer_token', 'partner-token');
        Http::fake([
            'partner.example/*' => Http::response(['ok' => true]),
        ]);

        Institution::query()->create([
            'SKcode' => 'SK-OUTBOUND',
            'AccountName' => '아웃바운드 기관',
            'Director' => null,
            'Phone' => null,
            'Address' => null,
            'Gubun' => null,
        ]);
        AccountInformation::query()->create([
            'SK_Code' => 'SK-OUTBOUND',
            'Account_Name' => '아웃바운드 기관',
            'CO' => 'CO 담당',
            'TR' => 'TR 담당',
            'CS' => 'CS 담당',
        ]);

        (new SyncInstitutionOutboundJob('SK-OUTBOUND', SyncOrigin::Local))->handle();

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && $request->url() === 'https://partner.example/internal/institutions/SK-OUTBOUND'
                && $request['institution_name'] === '아웃바운드 기관'
                && $request['co'] === 'CO 담당'
                && $request['tr'] === 'TR 담당'
                && $request['cs'] === 'CS 담당'
                && $request->hasHeader('Authorization', 'Bearer partner-token');
        });
    }

    public function test_filter_introduction_path_limits_list(): void
    {
        $user = auth()->user();
        $a = '필터A '.uniqid('', true);
        $b = '필터B '.uniqid('', true);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => $a,
            'Connected' => '인바운드 콜',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
            'created_by' => $user?->id,
        ]);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-02',
            'AccountName' => $b,
            'Connected' => '기타경로',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
            'created_by' => $user?->id,
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->set('filterIntroductionPath', '인바운드 콜')
            ->assertSee($a)
            ->assertDontSee($b);
    }

    public function test_regular_user_list_is_scoped_to_own_potential_targets(): void
    {
        $owner = User::factory()->create([
            'name' => 'Peter Kim',
            'email' => 'peter.kim@example.test',
            'is_admin' => false,
            'is_gs_brochure_admin' => false,
            'can_manage_store_inventory' => false,
        ]);
        $other = User::factory()->create([
            'name' => 'Unrelated Owner',
            'email' => 'other@example.test',
            'is_admin' => false,
        ]);

        $own = '내 잠재기관 '.uniqid('', true);
        $legacyOwn = '레거시 담당 잠재기관 '.uniqid('', true);
        $others = '타인 잠재기관 '.uniqid('', true);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => $own,
            'AccountManager' => 'Random Manager',
            'Connected' => '내 소개경로',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
            'created_by' => $owner->id,
        ]);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-02',
            'AccountName' => $legacyOwn,
            'AccountManager' => 'Peter.Kim',
            'Connected' => '레거시 소개경로',
            'Type' => '해지',
            'Gubun' => '해지상담',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'B',
            'created_by' => null,
        ]);
        CoNewTarget::query()->create([
            'Year' => 2025,
            'CreatedDate' => '2025-04-01',
            'AccountName' => $others,
            'AccountManager' => 'Unrelated Manager',
            'Connected' => '타인 소개경로',
            'Type' => '외부유입',
            'Gubun' => '외부유입방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'C',
            'created_by' => $other->id,
        ]);

        $this->be($owner);

        $component = Livewire::actingAs($owner)
            ->test(PotentialInstitutionList::class)
            ->assertSee($own)
            ->assertSee($legacyOwn);

        $visibleTargetNames = collect($component->viewData('targets')->items())
            ->pluck('AccountName')
            ->all();

        $this->assertContains($own, $visibleTargetNames);
        $this->assertContains($legacyOwn, $visibleTargetNames);
        $this->assertNotContains($others, $visibleTargetNames);

        $this->assertNotContains('타인 소개경로', $component->viewData('introductionPathList')->all());
        $this->assertNotContains('외부유입', $component->viewData('typeList')->all());
        $this->assertNotContains('Unrelated Manager', $component->viewData('managerList')->all());
    }

    public function test_admin_potential_target_list_keeps_full_visibility(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();

        $targetName = '관리자 전체조회 '.uniqid('', true);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => $targetName,
            'AccountManager' => 'Other Manager',
            'Connected' => '관리자 소개경로',
            'Type' => '외부유입',
            'Gubun' => '외부유입방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'C',
            'created_by' => $other->id,
        ]);

        Livewire::actingAs($admin)
            ->test(PotentialInstitutionList::class)
            ->assertSee($targetName)
            ->assertSee('관리자 소개경로')
            ->assertSee('외부유입');
    }

    public function test_manager_filter_matches_dotted_hyphen_underscore_names(): void
    {
        $user = User::factory()->create([
            'name' => 'Peter Kim',
            'is_admin' => false,
        ]);
        $this->be($user);

        $matchA = '담당자 표기A '.uniqid('', true);
        $matchB = '담당자 표기B '.uniqid('', true);
        $matchC = '담당자 표기C '.uniqid('', true);
        $nonMatch = '담당자 비매칭 '.uniqid('', true);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => $matchA,
            'AccountManager' => 'Peter.Kim',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
        ]);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-02',
            'AccountName' => $matchB,
            'AccountManager' => 'Peter-Kim',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
        ]);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-03',
            'AccountName' => $matchC,
            'AccountManager' => 'Peter_Kim',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
        ]);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-04',
            'AccountName' => $nonMatch,
            'AccountManager' => 'James.Kwak',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->set('filterManager', 'Peter Kim')
            ->assertSee($matchA)
            ->assertSee($matchB)
            ->assertSee($matchC)
            ->assertDontSee($nonMatch);
    }

    public function test_manager_filter_options_align_to_employee_master_label(): void
    {
        $user = User::factory()->create([
            'name' => 'Peter Kim',
            'email' => 'peter.kim@grapeseed.com',
            'is_admin' => false,
        ]);
        $this->be($user);

        DB::table('employee')->insert([
            'EMPNO' => 'E-PETER',
            'WORKDEPT' => 'A02',
            'KOREANAME' => '김봉철',
            'ENGLISHNAME' => 'Peter Kim',
            'EMAIL' => 'peter.kim@grapeseed.com',
            'STATUS' => 1,
        ]);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => '옵션정렬 A '.uniqid('', true),
            'AccountManager' => 'Peter.Kim',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
        ]);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-02',
            'AccountName' => '옵션정렬 B '.uniqid('', true),
            'AccountManager' => 'Peter_Kim',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
        ]);

        $html = Livewire::test(PotentialInstitutionList::class)
            ->set('filterYear', '1999') // 목록 행 노출을 없애 옵션 텍스트만 검증
            ->html();

        $this->assertSame(1, substr_count($html, '<option value="Peter Kim">Peter Kim</option>'));
        $this->assertStringNotContainsString('Peter.Kim', $html);
        $this->assertStringNotContainsString('Peter_Kim', $html);
    }

    public function test_filter_contract_possibility_letter(): void
    {
        $onlyA = 'PossA '.uniqid('', true);
        $onlyB = 'PossB '.uniqid('', true);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => $onlyA,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'A',
        ]);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-02',
            'AccountName' => $onlyB,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => 'B',
            'created_by' => auth()->id(),
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->set('filterContractPossibility', 'A')
            ->assertSee($onlyA)
            ->assertDontSee($onlyB);
    }

    public function test_detail_modal_commit_sets_contract(): void
    {
        Queue::fake();
        $name = '상세계약 QA '.uniqid('', true);
        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => $name,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => null,
            'created_by' => auth()->id(),
        ]);

        $id = (int) $row->ID;

        $component = Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', $id)
            ->assertSet('detailModalContract', '0')
            ->set('detailModalContract', '1')
            ->call('requestContractChange')
            ->assertSet('showContractChangeConfirmModal', true)
            ->call('confirmContractChange');

        $row->refresh();
        $this->assertTrue((bool) $row->IsContract);
        $this->assertNotNull($row->ContractedDate);

        $selected = $component->get('selectedTarget');
        $this->assertTrue($selected['is_contract'] ?? false);
        $this->assertSame('LEAD-'.$row->ID, $selected['account_code'] ?? null);
        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'LEAD-'.$row->ID,
            'AccountName' => $name,
        ]);
    }

    public function test_detail_modal_commit_clears_contract(): void
    {
        $name = '상세미계약 QA '.uniqid('', true);
        $sk = 'SK-UNCONTRACT-'.uniqid('', true);
        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountCode' => $sk,
            'AccountName' => $name,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => true,
            'ContractedDate' => '2026-01-15',
            'Possibility' => null,
            'created_by' => auth()->id(),
        ]);

        $id = (int) $row->ID;

        Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', $id)
            ->assertSet('detailModalContract', '1')
            ->set('detailModalContract', '0')
            ->call('requestContractChange')
            ->assertSet('showContractChangeConfirmModal', true)
            ->call('confirmContractChange');

        $row->refresh();
        $this->assertFalse((bool) $row->IsContract);
        $this->assertNull($row->ContractedDate);
    }

    public function test_uncontract_via_detail_modal_adds_visibility_override(): void
    {
        $name = '상세미계약숨김 QA '.uniqid('', true);
        $sk = 'SK-HIDE-'.uniqid('', true);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountCode' => $sk,
            'AccountName' => $name,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => true,
            'ContractedDate' => '2026-01-15',
            'Possibility' => null,
            'created_by' => auth()->id(),
        ]);

        $row = CoNewTarget::query()->where('AccountCode', $sk)->firstOrFail();

        Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', (int) $row->ID)
            ->set('detailModalContract', '0')
            ->call('requestContractChange')
            ->call('confirmContractChange');

        $this->assertDatabaseHas('institution_visibility_overrides', [
            'sk_code' => $sk,
            'hidden_reason' => 'uncontracted',
        ]);
        $this->assertNotNull(DB::table('institution_visibility_overrides')->where('sk_code', $sk)->value('hidden_at'));
    }

    public function test_recontract_after_uncontract_removes_visibility_override(): void
    {
        Queue::fake();

        $name = '재계약숨김해제 QA '.uniqid('', true);
        $sk = 'SK-RESHOW-'.uniqid('', true);
        Institution::query()->create([
            'SKcode' => $sk,
            'AccountName' => $name,
        ]);

        DB::table('institution_visibility_overrides')->insert([
            'sk_code' => $sk,
            'hidden_reason' => 'uncontracted',
            'hidden_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountCode' => $sk,
            'AccountName' => $name,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => null,
            'created_by' => auth()->id(),
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $this->assertDatabaseMissing('institution_visibility_overrides', [
            'sk_code' => $sk,
        ]);
    }

    public function test_delete_meeting_detail_removes_record_and_refreshes_list(): void
    {
        $admin = User::factory()->admin()->create();
        $accountName = '미팅삭제 QA '.uniqid('', true);
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-06',
            'AccountManager' => 'Mgr',
            'AccountCode' => null,
            'AccountName' => $accountName,
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => null,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => $accountName,
            'AccountManager' => 'Mgr',
            'MeetingDate' => '2026-04-10',
            'MeetingTime' => '10:00',
            'MeetingTime_End' => '11:00',
            'Description' => '삭제 대상 본문',
            'ConsultingType' => '전화',
            'Possibility' => 'A',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(PotentialInstitutionList::class)
            ->call('openDetailModal', (int) $target->ID)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->call('deleteMeetingDetail', (int) $detail->ID);

        $component->assertSet('showMeetingDetailModal', false);
        $component->assertHasNoErrors();
        $this->assertDatabaseMissing('S_CO_NewTarget_Detail', ['ID' => $detail->ID]);
        $meetings = $component->get('detailMeetings');
        $this->assertCount(0, $meetings);
    }

    public function test_delete_meeting_detail_rejects_contracted_target(): void
    {
        $admin = User::factory()->admin()->create();
        $name = '계약건미팅삭제 '.uniqid('', true);
        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => 'M1',
            'AccountCode' => 'SK-C',
            'AccountName' => $name,
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => true,
            'ContractedDate' => '2026-01-15',
            'Possibility' => null,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => $name,
            'AccountManager' => 'M1',
            'MeetingDate' => '2026-04-02',
            'MeetingTime' => '09:00',
            'MeetingTime_End' => null,
            'Description' => '계약 타깃 이력',
            'ConsultingType' => '전화',
            'Possibility' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(PotentialInstitutionList::class)
            ->call('openDetailModal', (int) $row->ID)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->call('deleteMeetingDetail', (int) $detail->ID)
            ->assertHasErrors(['deleteMeeting']);

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', ['ID' => $detail->ID]);
    }

    public function test_non_admin_cannot_delete_meeting_detail(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $accountName = '비관리자미팅삭제 '.uniqid('', true);
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-06',
            'AccountManager' => 'Mgr',
            'AccountCode' => null,
            'AccountName' => $accountName,
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => null,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => $accountName,
            'AccountManager' => 'Mgr',
            'MeetingDate' => '2026-04-10',
            'MeetingTime' => '10:00',
            'MeetingTime_End' => '11:00',
            'Description' => '비관리자 삭제 시도',
            'ConsultingType' => '전화',
            'Possibility' => 'A',
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionList::class)
            ->call('openDetailModal', (int) $target->ID)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->call('deleteMeetingDetail', (int) $detail->ID)
            ->assertHasErrors(['deleteMeeting']);

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', ['ID' => $detail->ID]);
    }

    public function test_delete_meeting_detail_scoped_to_target_account(): void
    {
        $admin = User::factory()->admin()->create();
        $nameA = '스코프A '.uniqid('', true);
        $nameB = '스코프B '.uniqid('', true);
        $targetA = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => 'MA',
            'AccountCode' => null,
            'AccountName' => $nameA,
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규(25년)',
            'Gubun' => '방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => null,
        ]);
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => 'MB',
            'AccountCode' => null,
            'AccountName' => $nameB,
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규(25년)',
            'Gubun' => '방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'Approaching' => 0,
            'Presenting' => 0,
            'Consulting' => 0,
            'Closing' => 0,
            'DroppedOut' => 0,
            'IsContract' => false,
            'ContractedDate' => null,
            'Possibility' => null,
        ]);
        $detailB = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => $nameB,
            'AccountManager' => 'MB',
            'MeetingDate' => '2026-04-03',
            'MeetingTime' => '10:00',
            'MeetingTime_End' => null,
            'Description' => 'B 이력',
            'ConsultingType' => '전화',
            'Possibility' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(PotentialInstitutionList::class)
            ->call('openDetailModal', (int) $targetA->ID)
            ->call('deleteMeetingDetail', (int) $detailB->ID)
            ->assertHasErrors(['deleteMeeting']);

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', ['ID' => $detailB->ID]);
    }

    public function test_detail_edit_saves_uncontracted_master_fields(): void
    {
        $name = '목록편집 QA '.uniqid('', true);
        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => auth()->user()?->name,
            'AccountName' => $name,
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 1,
            'GS_K' => 1,
            'GS_E' => 1,
            'Total' => 3,
            'IsContract' => false,
            'created_by' => auth()->id(),
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', (int) $row->ID)
            ->call('enterDetailEditMode')
            ->assertSet('detailEditMode', true)
            ->set('editAccountName', $name.'-수정')
            ->set('editType', '신규(25년)')
            ->set('editGubun', '해지방문')
            ->set('editLS', '0')
            ->set('editGSK', '0')
            ->set('editGSE', '0')
            ->call('saveDetailEdit')
            ->assertHasNoErrors()
            ->assertSet('detailEditMode', false)
            ->assertSet('selectedTarget.account_name', $name.'-수정');

        $row->refresh();
        $this->assertSame($name.'-수정', $row->AccountName);
        $this->assertSame(0, (int) $row->Total);
    }

    public function test_meeting_detail_edit_updates_meeting_record(): void
    {
        $name = '목록미팅수정 QA '.uniqid('', true);
        $manager = auth()->user()?->name ?? '관리자';

        $row = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => $manager,
            'AccountName' => $name,
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'created_by' => auth()->id(),
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => $name,
            'AccountManager' => $manager,
            'MeetingDate' => '2026-04-05',
            'MeetingTime' => '09:00',
            'MeetingTime_End' => '10:00',
            'Description' => '목록 수정 전',
            'ConsultingType' => '전화',
            'Possibility' => 'B',
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', (int) $row->ID)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->call('enterMeetingDetailEditMode')
            ->assertSet('meetingDetailEditMode', true)
            ->set('editMeetingDate', '2026-04-06')
            ->set('editMeetingTime', '13:00')
            ->set('editMeetingTimeEnd', '14:00')
            ->set('editConsultingType', '방문')
            ->set('editPossibility', 'A')
            ->set('editDescription', '목록 수정 후')
            ->call('saveMeetingDetailEdit')
            ->assertHasNoErrors()
            ->assertSet('meetingDetailEditMode', false)
            ->assertSet('selectedMeeting.consulting_type', '방문')
            ->assertSet('selectedMeeting.description', '목록 수정 후');

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'ID' => $detail->ID,
            'MeetingDate' => '2026-04-06 00:00:00',
            'MeetingTime' => '13:00',
            'MeetingTime_End' => '14:00',
            'ConsultingType' => '방문',
            'Possibility' => 'A',
            'Description' => '목록 수정 후',
        ]);
    }

    public function test_list_displays_computed_student_total_when_stored_total_is_zero(): void
    {
        $user = User::factory()->admin()->create();
        $name = '레거시합계 QA '.uniqid('', true);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => $user->name,
            'AccountName' => $name,
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 11,
            'GS_K' => 22,
            'GS_E' => 33,
            'Total' => 0,
            'IsContract' => false,
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionList::class)
            ->assertSee($name)
            ->assertSee('66');
    }
}
