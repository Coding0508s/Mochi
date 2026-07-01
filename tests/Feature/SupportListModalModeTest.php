<?php

namespace Tests\Feature;

use App\Livewire\SupportList;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SupportListModalModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportTables();
    }

    private function createSupportTables(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');
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
            $table->text('TO_Account')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
            $table->timestamp('CreatedDate')->nullable();
            $table->boolean('is_urgent')->default(false);
        });
    }

    public function test_open_detail_modal_is_view_only(): void
    {
        $record = $this->createSupportRecord(['TR_Name' => '담당자A']);

        $user = User::factory()->create(['name' => '담당자A']);

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openDetailModal', (int) $record->ID)
            ->assertSet('showModal', true)
            ->assertSet('modalViewOnly', true)
            ->assertSet('editingId', (int) $record->ID);
    }

    public function test_save_in_view_only_mode_does_not_persist_changes(): void
    {
        $record = $this->createSupportRecord([
            'TR_Name' => '담당자A',
            'TO_Account' => '원본 내용',
        ]);

        $user = User::factory()->create(['name' => '담당자A']);

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openDetailModal', (int) $record->ID)
            ->set('formToAccount', '변경 시도')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'ID' => $record->ID,
            'TO_Account' => '원본 내용',
        ]);
    }

    public function test_owner_can_start_edit_and_save(): void
    {
        $record = $this->createSupportRecord([
            'TR_Name' => '담당자A',
            'TO_Account' => '원본',
        ]);

        $user = User::factory()->create(['name' => '담당자A']);

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openDetailModal', (int) $record->ID)
            ->call('startModalEdit')
            ->assertSet('modalViewOnly', false)
            ->set('formToAccount', '수정 반영')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'ID' => $record->ID,
            'TO_Account' => '수정 반영',
        ]);
    }

    public function test_non_owner_cannot_update_via_gate_or_save(): void
    {
        $record = $this->createSupportRecord([
            'TR_Name' => '다른 담당',
            'TO_Account' => '원본',
        ]);

        $user = User::factory()->create(['name' => '본인']);

        $this->assertFalse(Gate::forUser($user)->allows('updateSupportRecord', $record));

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openDetailModal', (int) $record->ID)
            ->assertSet('modalViewOnly', true)
            ->assertSet('formToAccount', '원본');

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openDetailModal', (int) $record->ID)
            ->set('modalViewOnly', false)
            ->set('formToAccount', '무단 변경')
            ->call('save')
            ->assertSet('modalViewOnly', false);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'ID' => $record->ID,
            'TO_Account' => '원본',
        ]);
    }

    public function test_admin_can_edit_any_record(): void
    {
        $record = $this->createSupportRecord([
            'TR_Name' => '다른 담당',
            'TO_Account' => '원본',
        ]);

        $admin = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('updateSupportRecord', $record));

        Livewire::actingAs($admin)
            ->test(SupportList::class)
            ->call('openDetailModal', (int) $record->ID)
            ->call('startModalEdit')
            ->set('formToAccount', '관리자 수정')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'ID' => $record->ID,
            'TO_Account' => '관리자 수정',
        ]);
    }

    public function test_cancel_modal_edit_restores_view_only(): void
    {
        $record = $this->createSupportRecord([
            'TR_Name' => '담당자A',
            'TO_Account' => '원본',
        ]);

        $user = User::factory()->create(['name' => '담당자A']);

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openDetailModal', (int) $record->ID)
            ->call('startModalEdit')
            ->set('formToAccount', '임시 변경')
            ->call('cancelModalEdit')
            ->assertSet('modalViewOnly', true)
            ->assertSet('formToAccount', '원본');
    }

    public function test_filter_urgent_only_shows_only_urgent_records(): void
    {
        $urgent = $this->createSupportRecord([
            'SK_Code' => 'SK-URG-1',
            'Account_Name' => '긴급 기관',
            'is_urgent' => true,
        ]);

        $normal = $this->createSupportRecord([
            'SK_Code' => 'SK-NOR-1',
            'Account_Name' => '일반 기관',
            'is_urgent' => false,
        ]);

        $user = User::factory()->create(['name' => '담당자A']);

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->assertSee((string) $urgent->Account_Name)
            ->assertSee((string) $normal->Account_Name)
            ->set('filterUrgentOnly', true)
            ->assertSee((string) $urgent->Account_Name)
            ->assertDontSee((string) $normal->Account_Name);
    }

    public function test_year_filter_options_list_distinct_years_from_support_records(): void
    {
        $this->createSupportRecord(['Year' => 2026, 'Support_Date' => '2026-06-18']);
        $this->createSupportRecord([
            'SK_Code' => 'SK-MODAL-2',
            'Account_Name' => '두번째 기관',
            'Year' => 2025,
            'Support_Date' => '2025-12-01',
        ]);

        $user = User::factory()->create(['name' => '담당자A']);

        $years = Livewire::actingAs($user)
            ->test(SupportList::class)
            ->viewData('years');

        $this->assertSame([2026, 2025], $years->all());
    }

    public function test_year_filter_matches_support_date_shown_in_list(): void
    {
        $this->createSupportRecord([
            'Year' => 2025,
            'Support_Date' => '2026-02-27',
            'Account_Name' => '2026년 지원',
        ]);
        $this->createSupportRecord([
            'SK_Code' => 'SK-MODAL-2',
            'Account_Name' => '2025년 지원',
            'Year' => 2025,
            'Support_Date' => '2025-12-01',
        ]);

        $user = User::factory()->create(['name' => '담당자A']);

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->set('filterYear', '2026')
            ->assertSee('2026년 지원')
            ->assertDontSee('2025년 지원');
    }

    public function test_contract_upload_modal_institution_select_does_not_error(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CONTRACT-MODAL',
            'AccountName' => '계약 업로드 기관',
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openContractUploadModal')
            ->set('contractSkCode', 'SK-CONTRACT-MODAL')
            ->assertSet('contractSkCode', 'SK-CONTRACT-MODAL')
            ->assertSee('계약 업로드 기관');
    }

    public function test_contract_upload_modal_tolerates_poisoned_institutions_cache(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CONTRACT-MODAL',
            'AccountName' => '계약 업로드 기관',
        ]);

        Cache::put('support-list:institutions-for-modal:v2', [
            'SK-POISON-STRING',
            [
                'SKcode' => 'SK-CONTRACT-MODAL',
                'AccountName' => '계약 업로드 기관',
            ],
        ], now()->addMinutes(10));

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openContractUploadModal')
            ->set('contractSkCode', 'SK-CONTRACT-MODAL')
            ->assertSet('contractSkCode', 'SK-CONTRACT-MODAL')
            ->assertSee('[SK-CONTRACT-MODAL]');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSupportRecord(array $overrides = []): SupportRecord
    {
        $skCode = (string) ($overrides['SK_Code'] ?? 'SK-MODAL-1');
        $accountName = (string) ($overrides['Account_Name'] ?? '모달 테스트 기관');

        Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => $accountName,
        ]);

        return SupportRecord::query()->create(array_merge([
            'Year' => 2026,
            'SK_Code' => $skCode,
            'Account_Name' => $accountName,
            'TR_Name' => 'CO',
            'Support_Date' => '2026-04-01',
            'Meet_Time' => '09:00:00',
            'Support_Type' => '전화',
            'Target' => '참석',
            'TO_Account' => '내용',
            'Status' => '진행중',
            'CreatedDate' => now(),
        ], $overrides));
    }
}
