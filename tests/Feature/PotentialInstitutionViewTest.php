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
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
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
}
