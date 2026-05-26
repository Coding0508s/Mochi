<?php

namespace Tests\Feature;

use App\Livewire\SupportList;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSupportRecord(array $overrides = []): SupportRecord
    {
        Institution::query()->create([
            'SKcode' => 'SK-MODAL-1',
            'AccountName' => '모달 테스트 기관',
        ]);

        return SupportRecord::query()->create(array_merge([
            'Year' => 2026,
            'SK_Code' => 'SK-MODAL-1',
            'Account_Name' => '모달 테스트 기관',
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
