<?php

namespace Tests\Feature;

use App\Livewire\PotentialInstitutionList;
use App\Models\AccountInformation;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use App\Services\PotentialInstitutionSkCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class PotentialInstitutionListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacyPotentialInstitutionTables();
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

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->string('EnglishName', 255)->nullable();
            $table->string('PortalAccountName', 255)->nullable();
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
        $accountName = 'SK코드 생략 QA '.uniqid('', true);

        Livewire::test(PotentialInstitutionList::class)
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
        $this->assertDatabaseMissing('S_AccountName', [
            'AccountName' => $accountName,
        ]);
        $this->assertDatabaseMissing('S_Account_Information', [
            'Account_Name' => $accountName,
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

    public function test_save_new_target_with_support_report_creates_support_record(): void
    {
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
            'MeetingDate' => '2026-04-06',
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
        $name = '계약완료 QA '.uniqid('', true);
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
            'Possibility' => 'B',
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $row->refresh();
        $this->assertTrue((bool) $row->IsContract);
        $this->assertNotNull($row->ContractedDate);
        $expectedSk = 'LEAD-'.$row->ID;
        $this->assertSame($expectedSk, trim((string) $row->AccountCode));
        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $expectedSk,
            'AccountName' => $name,
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $expectedSk,
            'Account_Name' => $name,
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $row->refresh();
        $this->assertTrue((bool) $row->IsContract);
    }

    public function test_mark_contract_complete_does_not_duplicate_institution_when_sk_already_in_list(): void
    {
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
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $this->assertSame(1, Institution::query()->where('SKcode', $existingSk)->count());
    }

    public function test_filter_introduction_path_limits_list(): void
    {
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
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->set('filterIntroductionPath', '인바운드 콜')
            ->assertSee($a)
            ->assertDontSee($b);
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
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->set('filterContractPossibility', 'A')
            ->assertSee($onlyA)
            ->assertDontSee($onlyB);
    }

    public function test_detail_modal_commit_sets_contract(): void
    {
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
        ]);

        $id = (int) $row->ID;

        $component = Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', $id)
            ->assertSet('detailModalContract', '0')
            ->set('detailModalContract', '1')
            ->call('commitDetailContract');

        $row->refresh();
        $this->assertTrue((bool) $row->IsContract);
        $this->assertNotNull($row->ContractedDate);

        $selected = $component->get('selectedTarget');
        $this->assertTrue($selected['is_contract'] ?? false);
        $expectedSk = 'LEAD-'.$id;
        $this->assertSame($expectedSk, $selected['account_code'] ?? null);
        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $expectedSk,
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
        ]);

        Institution::query()->create([
            'SKcode' => $sk,
            'AccountName' => $name,
            'Director' => null,
            'Phone' => null,
            'Address' => null,
            'Gubun' => null,
        ]);
        AccountInformation::query()->create([
            'SK_Code' => $sk,
            'Account_Name' => $name,
        ]);

        $id = (int) $row->ID;

        Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', $id)
            ->assertSet('detailModalContract', '1')
            ->set('detailModalContract', '0')
            ->call('commitDetailContract');

        $row->refresh();
        $this->assertFalse((bool) $row->IsContract);
        $this->assertNull($row->ContractedDate);
        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $sk,
        ]);
        $this->assertDatabaseHas('institution_visibility_overrides', [
            'sk_code' => $sk,
            'hidden_reason' => 'uncontracted',
        ]);
    }

    public function test_detail_modal_commit_sets_contract_clears_hidden_override(): void
    {
        $name = '재계약숨김해제 QA '.uniqid('', true);
        $sk = 'SK-RECONTRACT-'.uniqid('', true);
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
        ]);

        Institution::query()->create([
            'SKcode' => $sk,
            'AccountName' => $name,
            'Director' => null,
            'Phone' => null,
            'Address' => null,
            'Gubun' => null,
        ]);
        AccountInformation::query()->create([
            'SK_Code' => $sk,
            'Account_Name' => $name,
        ]);
        DB::table('institution_visibility_overrides')->insert([
            'sk_code' => $sk,
            'hidden_reason' => 'uncontracted',
            'hidden_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('openDetailModal', (int) $row->ID)
            ->set('detailModalContract', '1')
            ->call('commitDetailContract');

        $row->refresh();
        $this->assertTrue((bool) $row->IsContract);
        $this->assertDatabaseMissing('institution_visibility_overrides', [
            'sk_code' => $sk,
        ]);
        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => $name,
        ]);
    }
}
