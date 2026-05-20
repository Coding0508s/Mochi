<?php

namespace Tests\Feature;

use App\Livewire\PotentialInstitutionMeetingForm;
use App\Livewire\PotentialInstitutionView;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class PotentialInstitutionViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPotentialInstitutionTables();
    }

    private function createPotentialInstitutionTables(): void
    {
        Schema::dropIfExists('S_CO_NewTarget_Detail');
        Schema::dropIfExists('S_CO_NewTarget');
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('sk_code_requests');

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

        Schema::create('sk_code_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('co_new_target_id');
            $table->string('institution_name', 200);
            $table->string('temp_sk_code', 64);
            $table->string('final_sk_code', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_created_mode_lists_only_targets_in_selected_month(): void
    {
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-15',
            'AccountName' => 'AprilOnly',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 1,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 1,
        ]);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-05-02',
            'AccountName' => 'MayOnly',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 1,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 1,
        ]);

        Livewire::test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'created')
            ->assertSee('AprilOnly')
            ->assertDontSee('MayOnly');
    }

    public function test_meeting_mode_lists_only_details_in_selected_month(): void
    {
        CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => 'MeetingApril',
            'MeetingDate' => '2026-04-08',
            'ConsultingType' => '콜',
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => 'MeetingJune',
            'MeetingDate' => '2026-06-01',
            'ConsultingType' => '방문',
        ]);

        Livewire::test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'meeting')
            ->assertSee('MeetingApril')
            ->assertDontSee('MeetingJune');
    }

    public function test_year_mode_lists_targets_across_selected_year(): void
    {
        CoNewTarget::query()->create([
            'Year' => 2025,
            'CreatedDate' => '2025-11-20',
            'AccountName' => 'Year2025Row',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 2,
            'GS_K' => 3,
            'GS_E' => 1,
            'Total' => 6,
        ]);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-03-10',
            'AccountName' => 'Year2026Row',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
        ]);

        Livewire::test(PotentialInstitutionView::class)
            ->set('periodGranularity', 'year')
            ->set('filterYear', '2026')
            ->set('dateBasis', 'created')
            ->assertSee('Year2026Row')
            ->assertDontSee('Year2025Row');
    }

    public function test_year_mode_meeting_lists_details_in_selected_year(): void
    {
        CoNewTargetDetail::query()->create([
            'Year' => 2025,
            'AccountName' => 'M2025',
            'MeetingDate' => '2025-12-01',
            'ConsultingType' => '콜',
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => 'M2026',
            'MeetingDate' => '2026-02-15',
            'ConsultingType' => '방문',
        ]);

        Livewire::test(PotentialInstitutionView::class)
            ->set('periodGranularity', 'year')
            ->set('filterYear', '2026')
            ->set('dateBasis', 'meeting')
            ->assertSee('M2026')
            ->assertDontSee('M2025');
    }

    public function test_switching_year_month_resets_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            CoNewTarget::query()->create([
                'Year' => 2026,
                'CreatedDate' => '2026-04-'.str_pad((string) (($i % 27) + 1), 2, '0', STR_PAD_LEFT),
                'AccountName' => 'Bulk '.$i,
                'Type' => '신규',
                'Gubun' => '방문',
                'LS' => 0,
                'GS_K' => 0,
                'GS_E' => 0,
                'Total' => 0,
            ]);
        }

        $component = Livewire::test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'created')
            ->call('gotoPage', 2);

        $component->set('yearMonth', '2026-05')
            ->assertSet('paginators.page', 1);
    }

    public function test_route_potential_institutions_view_is_registered(): void
    {
        $this->assertSame(
            url('/potential-institutions/view'),
            route('potential-institutions.view'),
        );
    }

    public function test_detail_modal_lists_support_by_potential_target_id_when_sk_missing(): void
    {
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => 'Mgr',
            'AccountCode' => null,
            'AccountName' => 'View SK없음 기관',
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규',
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

        SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => null,
            'potential_target_id' => $target->ID,
            'Account_Name' => 'View SK없음 기관',
            'TR_Name' => 'CO',
            'Support_Date' => '2026-04-11',
            'Meet_Time' => '10:00:00',
            'Support_Type' => '전화',
            'Target' => null,
            'Issue' => null,
            'TO_Account' => '내용',
            'TO_Depart' => null,
            'Status' => '진행중',
            'CompletedDate' => null,
            'CreatedDate' => now(),
        ]);

        $component = Livewire::test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'created')
            ->call('openTargetDetail', (int) $target->ID);

        $rows = $component->get('detailSupportRecords');
        $this->assertCount(1, $rows);
        $this->assertSame('전화', $rows[0]['support_type'] ?? null);
        $this->assertStringContainsString('내용', (string) ($rows[0]['to_account'] ?? ''));
    }

    public function test_detail_meeting_detail_modal_shows_full_description(): void
    {
        $longBody = 'FULL_MEETING_BODY_'.str_repeat('x', 180);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => 'Mgr',
            'AccountCode' => null,
            'AccountName' => 'ModalLong 기관',
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규',
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

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => 'ModalLong 기관',
            'AccountManager' => 'Mgr',
            'MeetingDate' => '2026-04-05',
            'MeetingTime' => '10:00',
            'MeetingTime_End' => '11:00',
            'Description' => $longBody,
            'ConsultingType' => '전화',
            'Possibility' => 'A',
        ]);

        Livewire::test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'created')
            ->call('openTargetDetail', (int) $target->ID)
            ->assertSet('showMeetingDetailModal', false)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->assertSet('showMeetingDetailModal', true)
            ->assertSee($longBody);
    }

    public function test_delete_meeting_detail_removes_record(): void
    {
        $user = User::factory()->admin()->create();
        $accountName = '뷰 미팅삭제 '.uniqid('', true);
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => 'Mgr',
            'AccountCode' => null,
            'AccountName' => $accountName,
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규',
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

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => $accountName,
            'AccountManager' => 'Mgr',
            'MeetingDate' => '2026-04-11',
            'MeetingTime' => '14:00',
            'MeetingTime_End' => null,
            'Description' => '뷰에서 삭제',
            'ConsultingType' => '전화',
            'Possibility' => null,
        ]);

        $component = Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'created')
            ->call('openTargetDetail', (int) $target->ID)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->call('deleteMeetingDetail', (int) $detail->ID);

        $component->assertSet('showMeetingDetailModal', false);
        $component->assertHasNoErrors();
        $this->assertDatabaseMissing('S_CO_NewTarget_Detail', ['ID' => $detail->ID]);
        $this->assertCount(0, $component->get('detailMeetings'));
    }

    public function test_delete_meeting_detail_rejects_contracted_target_on_view(): void
    {
        $user = User::factory()->admin()->create();
        $name = '뷰 계약 미팅삭제 '.uniqid('', true);
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => 'M1',
            'AccountCode' => 'SK-V',
            'AccountName' => $name,
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규',
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
            'IsContract' => true,
            'ContractedDate' => '2026-01-10',
            'Possibility' => null,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => $name,
            'AccountManager' => 'M1',
            'MeetingDate' => '2026-04-02',
            'MeetingTime' => '09:00',
            'MeetingTime_End' => null,
            'Description' => '계약 타깃',
            'ConsultingType' => '전화',
            'Possibility' => null,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'created')
            ->call('openTargetDetail', (int) $target->ID)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->call('deleteMeetingDetail', (int) $detail->ID)
            ->assertHasErrors(['deleteMeeting']);

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', ['ID' => $detail->ID]);
    }

    public function test_non_admin_cannot_delete_meeting_detail_on_view(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $accountName = '뷰 비관리자미팅삭제 '.uniqid('', true);
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => 'Mgr',
            'AccountCode' => null,
            'AccountName' => $accountName,
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규',
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

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => $accountName,
            'AccountManager' => 'Mgr',
            'MeetingDate' => '2026-04-11',
            'MeetingTime' => '14:00',
            'MeetingTime_End' => null,
            'Description' => '뷰 비관리자 삭제 시도',
            'ConsultingType' => '전화',
            'Possibility' => null,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'created')
            ->call('openTargetDetail', (int) $target->ID)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->call('deleteMeetingDetail', (int) $detail->ID)
            ->assertHasErrors(['deleteMeeting']);

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', ['ID' => $detail->ID]);
    }

    public function test_meeting_form_creates_detail_for_uncontracted_target(): void
    {
        $user = User::factory()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => '담당A',
            'AccountCode' => null,
            'AccountName' => '미팅폼 테스트 기관',
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규',
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
            'Possibility' => 'B',
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionMeetingForm::class, ['coNewTargetId' => (int) $target->ID])
            ->set('meetingDate', '2026-04-18')
            ->set('consultingType', '재방문')
            ->set('description', '추가 미팅 메모')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'AccountName' => '미팅폼 테스트 기관',
            'ConsultingType' => '재방문',
            'AccountManager' => '담당A',
        ]);

        $detail = CoNewTargetDetail::query()
            ->where('AccountName', '미팅폼 테스트 기관')
            ->whereDate('MeetingDate', '2026-04-18')
            ->first();
        $this->assertNotNull($detail);
        $this->assertStringContainsString('추가 미팅', (string) $detail->Description);
    }

    public function test_meeting_form_does_not_send_mail_when_notify_addresses_empty(): void
    {
        Mail::fake();

        config(['support_report_mail.notify_addresses' => []]);

        $user = User::factory()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => '담당A',
            'AccountCode' => null,
            'AccountName' => '메일 없음 미팅 기관',
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규',
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
            'Possibility' => 'B',
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionMeetingForm::class, ['coNewTargetId' => (int) $target->ID])
            ->set('meetingDate', '2026-04-18')
            ->set('consultingType', '재방문')
            ->set('description', '메일 미발송 미팅 메모')
            ->call('save')
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_meeting_form_rejects_contracted_target(): void
    {
        $user = User::factory()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => null,
            'AccountCode' => 'SK-DONE',
            'AccountName' => '계약 완료 기관',
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
            ->test(PotentialInstitutionMeetingForm::class, ['coNewTargetId' => (int) $target->ID])
            ->set('meetingDate', '2026-04-18')
            ->set('consultingType', '재방문')
            ->call('save')
            ->assertHasErrors(['meetingForm']);
    }

    public function test_delete_uncontracted_target_removes_target_details_and_support_records(): void
    {
        $user = User::factory()->admin()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => '삭제테스트담당',
            'AccountCode' => null,
            'AccountName' => '삭제테스트기관',
            'Address' => null,
            'Director' => null,
            'Phone' => null,
            'Connected' => null,
            'Type' => '신규',
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
            'Possibility' => 'B',
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => '삭제테스트기관',
            'AccountManager' => '삭제테스트담당',
            'MeetingDate' => '2026-04-12 00:00:00',
            'ConsultingType' => '콜',
        ]);

        SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => null,
            'potential_target_id' => $target->ID,
            'Account_Name' => '삭제테스트기관',
            'TR_Name' => 'CO',
            'Support_Date' => '2026-04-11',
            'Meet_Time' => '10:00:00',
            'Support_Type' => '전화',
            'Target' => null,
            'Issue' => null,
            'TO_Account' => '내용',
            'TO_Depart' => null,
            'Status' => '진행중',
            'CompletedDate' => null,
            'CreatedDate' => now(),
        ]);

        $otherTarget = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => '다른담당',
            'AccountCode' => null,
            'AccountName' => '다른잠재기관',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'Possibility' => 'B',
        ]);

        $this->createSkCodeRequest((int) $target->ID, 'LEAD-DELETE');
        $this->createSkCodeRequest((int) $otherTarget->ID, 'LEAD-KEEP');

        Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->call('openTargetDetail', (int) $target->ID)
            ->assertSet('showDetailModal', true)
            ->call('deleteUncontractedTarget', (int) $target->ID)
            ->assertHasNoErrors()
            ->assertSet('showDetailModal', false);

        $this->assertDatabaseMissing('S_CO_NewTarget', [
            'ID' => $target->ID,
        ]);
        $this->assertDatabaseMissing('S_CO_NewTarget_Detail', [
            'AccountName' => '삭제테스트기관',
            'AccountManager' => '삭제테스트담당',
        ]);
        $this->assertDatabaseMissing('S_SupportInfo_Account', [
            'potential_target_id' => $target->ID,
        ]);
        $this->assertDatabaseMissing('sk_code_requests', [
            'co_new_target_id' => $target->ID,
            'temp_sk_code' => 'LEAD-DELETE',
        ]);
        $this->assertDatabaseHas('sk_code_requests', [
            'co_new_target_id' => $otherTarget->ID,
            'temp_sk_code' => 'LEAD-KEEP',
        ]);
    }

    public function test_delete_rejects_contracted_target(): void
    {
        $user = User::factory()->admin()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => null,
            'AccountCode' => 'SK-LOCK',
            'AccountName' => '계약됨 삭제불가',
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
            ->test(PotentialInstitutionView::class)
            ->call('openTargetDetail', (int) $target->ID)
            ->call('deleteUncontractedTarget', (int) $target->ID)
            ->assertHasErrors(['deleteTarget']);

        $this->assertDatabaseHas('S_CO_NewTarget', [
            'ID' => $target->ID,
            'AccountName' => '계약됨 삭제불가',
        ]);
    }

    public function test_non_admin_cannot_delete_uncontracted_potential_target(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => '일반',
            'AccountCode' => null,
            'AccountName' => '비관리자 삭제 시도',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'Possibility' => null,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->call('openTargetDetail', (int) $target->ID)
            ->call('deleteUncontractedTarget', (int) $target->ID);

        $this->assertDatabaseHas('S_CO_NewTarget', [
            'ID' => $target->ID,
            'AccountName' => '비관리자 삭제 시도',
        ]);
    }

    public function test_detail_edit_mode_shows_computed_total_while_editing(): void
    {
        $user = User::factory()->admin()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => $user->name,
            'AccountName' => '합계미리보기',
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->call('openTargetDetail', (int) $target->ID)
            ->call('enterDetailEditMode')
            ->set('editLS', '11')
            ->set('editGSK', '22')
            ->set('editGSE', '33')
            ->assertSee('66');
    }

    public function test_detail_edit_saves_uncontracted_master_fields(): void
    {
        $user = User::factory()->admin()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => $user->name,
            'AccountName' => '편집전기관',
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 1,
            'GS_K' => 1,
            'GS_E' => 1,
            'Total' => 3,
            'IsContract' => false,
            'Possibility' => 'B',
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->call('openTargetDetail', (int) $target->ID)
            ->call('enterDetailEditMode')
            ->assertSet('detailEditMode', true)
            ->set('editAccountName', '편집후기관')
            ->set('editType', '신규(25년)')
            ->set('editGubun', '해지방문')
            ->set('editLS', '2')
            ->set('editGSK', '3')
            ->set('editGSE', '4')
            ->call('saveDetailEdit')
            ->assertHasNoErrors()
            ->assertSet('detailEditMode', false)
            ->assertSet('selectedTarget.account_name', '편집후기관');

        $target->refresh();
        $this->assertSame('편집후기관', $target->AccountName);
        $this->assertSame(9, (int) $target->Total);
    }

    public function test_contracted_target_cannot_enter_detail_edit_mode(): void
    {
        $user = User::factory()->admin()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountName' => '계약기관',
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => true,
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->call('openTargetDetail', (int) $target->ID)
            ->call('enterDetailEditMode')
            ->assertSet('detailEditMode', false);
    }

    public function test_meeting_detail_edit_updates_meeting_record(): void
    {
        $user = User::factory()->admin()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => $user->name,
            'AccountName' => '미팅수정기관',
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'created_by' => $user->id,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => '미팅수정기관',
            'AccountManager' => $user->name,
            'MeetingDate' => '2026-04-10',
            'MeetingTime' => '10:00',
            'MeetingTime_End' => '11:00',
            'Description' => '수정 전 내용',
            'ConsultingType' => '전화',
            'Possibility' => 'B',
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->call('openTargetDetail', (int) $target->ID)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->call('enterMeetingDetailEditMode')
            ->assertSet('meetingDetailEditMode', true)
            ->set('editMeetingDate', '2026-04-12')
            ->set('editMeetingTime', '14:00')
            ->set('editMeetingTimeEnd', '15:00')
            ->set('editConsultingType', '방문')
            ->set('editPossibility', 'A')
            ->set('editDescription', '수정 후 내용')
            ->call('saveMeetingDetailEdit')
            ->assertHasNoErrors()
            ->assertSet('meetingDetailEditMode', false)
            ->assertSet('selectedMeeting.consulting_type', '방문')
            ->assertSet('selectedMeeting.description', '수정 후 내용');

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'ID' => $detail->ID,
            'MeetingDate' => '2026-04-12 00:00:00',
            'MeetingTime' => '14:00',
            'MeetingTime_End' => '15:00',
            'ConsultingType' => '방문',
            'Possibility' => 'A',
            'Description' => '수정 후 내용',
        ]);
    }

    public function test_contracted_target_cannot_enter_meeting_detail_edit_mode(): void
    {
        $user = User::factory()->admin()->create();
        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-10',
            'AccountManager' => $user->name,
            'AccountName' => '계약미팅기관',
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => true,
            'created_by' => $user->id,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => '계약미팅기관',
            'AccountManager' => $user->name,
            'MeetingDate' => '2026-04-10',
            'ConsultingType' => '전화',
        ]);

        Livewire::actingAs($user)
            ->test(PotentialInstitutionView::class)
            ->call('openTargetDetail', (int) $target->ID)
            ->call('openMeetingDetailModal', (int) $detail->ID)
            ->call('enterMeetingDetailEditMode')
            ->assertSet('meetingDetailEditMode', false);
    }

    private function createSkCodeRequest(int $targetId, string $tempSkCode): void
    {
        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => $targetId,
            'institution_name' => 'SK 요청 기관',
            'temp_sk_code' => $tempSkCode,
            'status' => 'pending',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
