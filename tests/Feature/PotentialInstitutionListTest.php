<?php

namespace Tests\Feature;

use App\Livewire\PotentialInstitutionList;
use App\Models\AccountInformation;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\Institution;
use App\Models\User;
use App\Services\PotentialInstitutionSkCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_save_new_target_allows_empty_sk_code(): void
    {
        $accountName = 'SK코드 생략 QA '.uniqid('', true);

        Livewire::test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', $accountName)
            ->set('newAccountCode', '')
            ->set('newType', '신규(25년)')
            ->set('newConsultingType', '신규기관방문')
            ->set('newMeetingDate', '2026-04-06')
            ->call('saveNewTarget')
            ->assertSet('showCreateModal', false)
            ->assertHasNoErrors();

        $target = CoNewTarget::query()->where('AccountName', $accountName)->first();
        $this->assertNotNull($target);
        $this->assertNotNull($target->AccountCode);
        $this->assertStringStartsWith('LEAD-', (string) $target->AccountCode);

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $target->AccountCode,
            'AccountName' => $accountName,
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $target->AccountCode,
            'Account_Name' => $accountName,
        ]);
    }

    public function test_save_new_target_persists_target_and_first_meeting_detail(): void
    {
        $accountName = 'QA 테스트 기관 '.uniqid('', true);
        $accountCode = 'SK-QA-'.uniqid();

        Livewire::test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', $accountName)
            ->set('newAccountCode', $accountCode)
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
            'AccountCode' => $accountCode,
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

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $accountCode,
            'AccountName' => $accountName,
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $accountCode,
            'Customer_Type' => '신규(25년)',
        ]);
    }

    public function test_save_rejects_duplicate_sk_already_in_institution_list(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-DUP-TEST',
            'AccountName' => '기존 기관',
            'Director' => null,
            'Phone' => null,
            'Address' => null,
            'Gubun' => null,
        ]);

        Livewire::test(PotentialInstitutionList::class)
            ->call('openCreateModal')
            ->set('newAccountName', '신규 이름')
            ->set('newAccountCode', 'SK-DUP-TEST')
            ->set('newType', '신규(25년)')
            ->set('newConsultingType', '신규기관방문')
            ->set('newMeetingDate', '2026-04-06')
            ->call('saveNewTarget')
            ->assertHasErrors(['newAccountCode']);
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

        Livewire::test(PotentialInstitutionList::class)
            ->call('markContractComplete', (int) $row->ID);

        $row->refresh();
        $this->assertTrue((bool) $row->IsContract);
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
    }

    public function test_detail_modal_commit_clears_contract(): void
    {
        $name = '상세미계약 QA '.uniqid('', true);
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
            'IsContract' => true,
            'ContractedDate' => '2026-01-15',
            'Possibility' => null,
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
    }
}
