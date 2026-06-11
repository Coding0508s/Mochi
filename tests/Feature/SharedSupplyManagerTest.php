<?php

namespace Tests\Feature;

use App\Livewire\SharedSupplyManager;
use App\Models\Institution;
use App\Models\SharedSupply;
use App\Models\SharedSupplyItem;
use App\Models\SharedSupplyLabel;
use App\Models\SupportRecord;
use App\Models\User;
use App\Models\VehicleUsageLog;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SharedSupplyManagerTest extends TestCase
{
    use RefreshDatabase;

    private function itemIdByCode(string $code): int
    {
        return (int) SharedSupplyItem::query()->where('code', $code)->value('id');
    }

    private function labelIdByCode(string $code): int
    {
        return (int) SharedSupplyLabel::query()->where('code', $code)->value('id');
    }

    public function test_shared_supplies_page_can_be_opened_by_non_admin_user(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('shared-supplies.index'))
            ->assertOk()
            ->assertSee('공용품관리')
            ->assertSee('검색')
            ->assertSee('일정 등록')
            ->assertDontSee('엑셀 업로드');
    }

    public function test_shared_supplies_page_shows_excel_upload_for_admin_user(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('shared-supplies.index'))
            ->assertOk()
            ->assertSee('엑셀 업로드');
    }

    public function test_excel_upload_is_visible_on_main_page_for_admin_user(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(SharedSupplyManager::class)
            ->assertSee('엑셀 업로드')
            ->assertSee('초기화 실행');
    }

    public function test_excel_upload_is_hidden_in_create_modal_for_non_admin_user(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->assertDontSee('엑셀 일괄 등록')
            ->assertDontSee('엑셀 업로드');
    }

    public function test_non_admin_cannot_call_excel_import_action(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('importFromExcel')
            ->assertForbidden();
    }

    public function test_non_admin_cannot_reset_shared_supply_data(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('resetConfirmationText', '초기화 실행')
            ->call('resetSharedSupplyData')
            ->assertForbidden();
    }

    public function test_admin_can_reset_shared_supply_management_data(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $item = SharedSupplyItem::query()->create([
            'code' => '90001',
            'name' => '초기화 테스트 공용품',
            'is_active' => true,
            'sort_order' => 999,
        ]);
        $label = SharedSupplyLabel::query()->create([
            'code' => '99',
            'name' => '초기화 테스트 라벨',
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $sharedSupply = SharedSupply::query()->create([
            'user_id' => $admin->id,
            'starts_at' => Carbon::parse('2026-06-11 09:00'),
            'ends_at' => Carbon::parse('2026-06-11 10:00'),
            'shared_supply_item_id' => $item->id,
            'shared_supply_label_id' => $label->id,
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '초기화 대상',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        DB::table('shared_supply_user_mappings')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(SharedSupplyManager::class)
            ->set('resetConfirmationText', '초기화 실행')
            ->call('resetSharedSupplyData')
            ->assertHasNoErrors()
            ->assertSet('resetConfirmationText', '');

        $this->assertDatabaseCount('shared_supplies', 0);
        $this->assertDatabaseCount('vehicle_usage_logs', 0);
        $this->assertDatabaseHas('shared_supply_items', [
            'code' => '00003',
            'name' => '04부8326 (투싼/경유)-구미김천역',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('shared_supply_items', [
            'code' => '00028',
            'name' => '[건강검진] 건강검진',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('shared_supply_labels', [
            'code' => '01',
            'name' => '차량배차',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('shared_supply_labels', [
            'code' => '02',
            'name' => '회의실',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('shared_supply_user_mappings', 0);
        $this->assertDatabaseMissing('team_schedules', [
            'source_type' => 'shared_supply',
        ]);
    }

    public function test_reset_shared_supply_data_requires_exact_confirmation_phrase(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        SharedSupplyItem::query()->create([
            'code' => '90001',
            'name' => '초기화 확인 공용품',
            'is_active' => true,
            'sort_order' => 999,
        ]);

        Livewire::actingAs($admin)
            ->test(SharedSupplyManager::class)
            ->set('resetConfirmationText', '초기화')
            ->call('resetSharedSupplyData')
            ->assertHasErrors(['resetConfirmationText']);

        $this->assertGreaterThan(0, SharedSupplyItem::query()->count());
    }

    public function test_user_can_register_shared_supply_booking(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-03')
            ->set('startTime', '10:00')
            ->set('endTime', '12:00')
            ->set('sharedSupplyItemId', $this->itemIdByCode('00003'))
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('scheduleCategoryCode', '006')
            ->set('title', '세미나 준비')
            ->set('purpose', '센터 방문 준비')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('공용품 사용 내역이 저장되었습니다.');

        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $user->id,
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'schedule_category_code' => '006',
            'title' => '세미나 준비',
            'purpose' => '센터 방문 준비',
        ]);
    }

    public function test_conflicting_time_slot_is_blocked_for_same_item(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-04 09:00'),
            'ends_at' => Carbon::parse('2026-06-04 11:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '기존 건',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-04')
            ->set('startTime', '10:00')
            ->set('endTime', '12:00')
            ->set('sharedSupplyItemId', $this->itemIdByCode('00003'))
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('vehicleUsagePurpose', '일반업무')
            ->set('vehicleOdometerBefore', 1000)
            ->set('vehicleOdometerAfter', 1020)
            ->set('purpose', '시간 겹침')
            ->call('save')
            ->assertHasErrors(['startTime']);

        $this->assertDatabaseCount('shared_supplies', 1);
    }

    public function test_conflicting_time_slot_is_allowed_for_non_reservation_titles(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-04')
            ->set('startTime', '09:00')
            ->set('endTime', '11:00')
            ->set('title', '[출장] 출장')
            ->set('purpose', '첫 번째 출장')
            ->call('save')
            ->assertHasNoErrors();

        $itemId = (int) SharedSupply::query()->where('title', '[출장] 출장')->value('shared_supply_item_id');

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-04')
            ->set('startTime', '10:00')
            ->set('endTime', '12:00')
            ->set('title', '[출장] 출장')
            ->set('purpose', '같은 공용품·겹치는 시간')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('shared_supplies', 2);
        $this->assertSame(
            2,
            SharedSupply::query()->where('shared_supply_item_id', $itemId)->count(),
        );
    }

    public function test_non_owner_cannot_update_other_users_shared_supply(): void
    {
        $owner = User::factory()->create([
            'is_admin' => false,
        ]);
        $viewer = User::factory()->create([
            'is_admin' => false,
        ]);

        $supply = SharedSupply::query()->create([
            'user_id' => $owner->id,
            'starts_at' => Carbon::parse('2026-06-05 13:00'),
            'ends_at' => Carbon::parse('2026-06-05 15:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00014'),
            'shared_supply_label_id' => $this->labelIdByCode('02'),
            'title' => '원본 제목',
            'purpose' => '원본 적요',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($viewer)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->set('title', '수정 시도')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseHas('shared_supplies', [
            'id' => $supply->id,
            'title' => '원본 제목',
        ]);
    }

    public function test_edit_modal_shows_supply_owner_name_instead_of_logged_in_user(): void
    {
        $owner = User::factory()->create([
            'name' => '김영일',
            'is_admin' => false,
        ]);
        $admin = User::factory()->create([
            'name' => 'Test User',
            'is_admin' => true,
        ]);

        $supply = SharedSupply::query()->create([
            'user_id' => $owner->id,
            'starts_at' => Carbon::parse('2026-06-08 08:20'),
            'ends_at' => Carbon::parse('2026-06-08 08:30'),
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '일반업무',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($admin)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->assertSet('vehicleUserName', '김영일');
    }

    public function test_create_modal_shows_logged_in_user_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->assertSet('vehicleUserName', 'Test User');
    }

    public function test_shared_supply_labels_are_seeded_from_migration(): void
    {
        $this->assertDatabaseHas('shared_supply_labels', [
            'code' => '01',
            'name' => '차량배차',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('shared_supply_labels', [
            'code' => '02',
            'name' => '회의실',
            'is_active' => true,
        ]);
    }

    public function test_date_cell_is_merged_with_rowspan_for_same_day_rows(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-06 09:00'),
            'ends_at' => Carbon::parse('2026-06-06 10:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '같은날 1',
            'purpose' => '목적1',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-06 11:00'),
            'ends_at' => Carbon::parse('2026-06-06 12:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00008'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '같은날 2',
            'purpose' => '목적2',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-07 09:00'),
            'ends_at' => Carbon::parse('2026-06-07 10:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00014'),
            'shared_supply_label_id' => $this->labelIdByCode('02'),
            'title' => '다음날 1',
            'purpose' => '목적3',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $html = Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->html();

        $this->assertStringContainsString('rowspan="2"', $html);
        // 모바일 카드(건당 1회) + 데스크톱 병합 셀(1회) = 같은 날짜 3회 표시
        $this->assertSame(3, substr_count($html, '2026/06/06'));
    }

    public function test_title_selection_filters_supply_item_list_in_modal(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        SharedSupplyItem::query()->create([
            'code' => '99999',
            'name' => '테스트 장비',
            'is_active' => true,
            'sort_order' => 99999,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[회의실] 신청 및 예약')
            ->assertSee('00013 - Grape Room')
            ->assertDontSee('00003 - 04부8326 (투싼/경유)-구미김천역')
            ->set('title', '[차량배차] 신청 및 예약')
            ->assertSee('00003 - 04부8326 (투싼/경유)-구미김천역')
            ->assertDontSee('00013 - Grape Room')
            ->set('title', '[회의실] 신청 및 예약 (팀 회의)')
            ->assertSee('00013 - Grape Room')
            ->assertDontSee('00003 - 04부8326 (투싼/경유)-구미김천역')
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->assertSee('00003 - 04부8326 (투싼/경유)-구미김천역')
            ->assertDontSee('00013 - Grape Room')
            ->assertDontSee('99999 - 테스트 장비')
            ->set('title', '[휴가] 연차휴가')
            ->assertSee('00018 - 오전 반차')
            ->assertSee('00019 - 오후 반차')
            ->assertSee('00020 - 시차')
            ->assertSee('00029 - 종일')
            ->assertDontSee('00003 - 04부8326 (투싼/경유)-구미김천역')
            ->assertDontSee('00013 - Grape Room')
            ->set('title', '[사내외업무] 사내외업무')
            ->assertSee('[사내외업무] 사내외업무')
            ->assertDontSee('00003 - 04부8326 (투싼/경유)-구미김천역')
            ->assertDontSee('00013 - Grape Room')
            ->assertDontSee('00018 - 오전 반차');
    }

    public function test_shared_supply_item_is_cleared_when_title_is_not_selected(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[출장] 출장')
            ->assertSet('sharedSupplyItemId', fn (?int $id): bool => $id !== null)
            ->set('title', '')
            ->assertSet('sharedSupplyItemId', null);
    }

    public function test_title_based_option_auto_creates_matching_supply_item(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        SharedSupplyItem::query()->where('name', '[출장] 출장')->delete();

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[출장] 출장')
            ->assertSee('[출장] 출장');

        $this->assertDatabaseHas('shared_supply_items', [
            'name' => '[출장] 출장',
            'is_active' => true,
        ]);
    }

    public function test_user_can_switch_between_tabs_and_see_tab_specific_sections(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-10 09:00'),
            'ends_at' => Carbon::parse('2026-06-10 10:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00013'),
            'shared_supply_label_id' => $this->labelIdByCode('02'),
            'title' => '[회의실] 신청 및 예약 (팀 회의)',
            'purpose' => '탭 전환 테스트',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->call('setActiveTab', 'daily')
            ->assertSet('activeTab', 'daily')
            ->assertSee('건')
            ->call('setActiveTab', 'monthly')
            ->assertSet('activeTab', 'monthly')
            ->assertSee('총 일정')
            ->call('setActiveTab', 'item')
            ->assertSet('activeTab', 'item')
            ->assertSee('Grape Room')
            ->call('setActiveTab', 'basic')
            ->assertSet('activeTab', 'basic');
    }

    public function test_date_range_filter_includes_past_dates_within_selected_range(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 09:00'));

        $user = User::factory()->create(['is_admin' => false]);

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-09 09:00'),
            'ends_at' => Carbon::parse('2026-06-09 10:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00013'),
            'shared_supply_label_id' => $this->labelIdByCode('02'),
            'title' => '과거 일정 제목',
            'purpose' => '과거 일정 적요',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-10 11:00'),
            'ends_at' => Carbon::parse('2026-06-10 12:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '당일 일정 제목',
            'purpose' => '당일 일정 적요',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->assertSee('과거 일정 제목')
            ->assertSee('당일 일정 제목')
            ->set('search', '과거 일정 제목')
            ->call('applySearch')
            ->assertSee('과거 일정 제목');

        $this->travelBack();
    }

    public function test_create_shared_supply_also_creates_team_schedule_event(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-09')
            ->set('startTime', '10:00')
            ->set('endTime', '11:00')
            ->set('title', '[회의실] 신청 및 예약')
            ->set('sharedSupplyItemId', $this->itemIdByCode('00013'))
            ->set('purpose', '캘린더 동기화 테스트')
            ->call('save')
            ->assertHasNoErrors();

        $supply = SharedSupply::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->assertDatabaseHas('team_schedules', [
            'source_type' => 'shared_supply',
            'source_id' => $supply->id,
            'user_id' => $user->id,
            'title' => '[회의실] 신청 및 예약',
            'type' => 'etc',
            'visibility' => 'team',
        ]);
    }

    public function test_delete_shared_supply_also_deletes_synced_team_schedule_event(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-10 09:00'),
            'ends_at' => Carbon::parse('2026-06-10 10:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[차량배차] 신청 및 예약',
            'purpose' => '삭제 동기화 테스트',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertDatabaseHas('team_schedules', [
            'source_type' => 'shared_supply',
            'source_id' => $supply->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->call('delete');

        $this->assertDatabaseMissing('shared_supplies', ['id' => $supply->id]);
        $this->assertDatabaseMissing('team_schedules', [
            'source_type' => 'shared_supply',
            'source_id' => $supply->id,
        ]);
    }

    public function test_basic_tab_loads_more_supplies_without_pagination(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 09:00'));

        $user = User::factory()->create(['is_admin' => false]);

        for ($index = 1; $index <= 45; $index++) {
            SharedSupply::query()->create([
                'user_id' => $user->id,
                'starts_at' => Carbon::parse('2026-06-10 09:00')->addMinutes($index),
                'ends_at' => Carbon::parse('2026-06-10 10:00')->addMinutes($index),
                'shared_supply_item_id' => $this->itemIdByCode('00003'),
                'shared_supply_label_id' => $this->labelIdByCode('01'),
                'title' => "무한스크롤 테스트 {$index}",
                'purpose' => '무한스크롤 검증',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->set('activeTab', 'basic')
            ->assertViewHas('supplies', fn ($supplies): bool => $supplies->count() === 40)
            ->call('loadMoreSupplies')
            ->assertViewHas('supplies', fn ($supplies): bool => $supplies->count() === 45)
            ->assertSee('모든 내역을 불러왔습니다')
            ->assertDontSee('Next &raquo;');

        $this->travelBack();
    }

    public function test_date_range_can_be_changed_by_direct_input(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('dateFrom', '2026-07-01')
            ->set('dateTo', '2026-07-31')
            ->assertSet('dateFrom', '2026-07-01')
            ->assertSet('dateTo', '2026-07-31');
    }

    public function test_user_can_toggle_between_reservation_and_personal_view(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 09:00'));

        $user = User::factory()->create(['is_admin' => false]);

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-10 10:00'),
            'ends_at' => Carbon::parse('2026-06-10 11:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00013'),
            'shared_supply_label_id' => $this->labelIdByCode('02'),
            'title' => '[회의실] 신청 및 예약 (팀 회의)',
            'purpose' => '예약형 일정',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-10 12:00'),
            'ends_at' => Carbon::parse('2026-06-10 13:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장] 출장',
            'purpose' => '일반 일정',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('activeTab', 'basic')
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->assertSee('[회의실] 신청 및 예약 (팀 회의)')
            ->assertSee('[출장] 출장')
            ->call('toggleReservationView', 'reservation')
            ->assertDontSee('[출장] 출장')
            ->assertSee('[회의실] 신청 및 예약 (팀 회의)')
            ->call('toggleReservationView', 'personal')
            ->assertDontSee('[회의실] 신청 및 예약 (팀 회의)')
            ->assertSee('[출장] 출장');

        $this->travelBack();
    }

    public function test_vehicle_booking_requires_vehicle_log_fields(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-12')
            ->set('startTime', '10:00')
            ->set('endTime', '11:00')
            ->set('sharedSupplyItemId', $this->itemIdByCode('00003'))
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('purpose', '차량 운행 테스트')
            ->call('save')
            ->assertHasErrors(['vehicleUsagePurpose', 'vehicleOdometerBefore'])
            ->assertHasNoErrors(['vehicleOdometerAfter']);
    }

    public function test_vehicle_usage_purpose_select_shows_fixed_options_on_create_modal(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->assertSee('00001 - 일반업무')
            ->assertSee('00002 - 출퇴근')
            ->assertSee('00003 - 업무외')
            ->assertSee('00004 - 신규기관 방문')
            ->assertSee('00005 - 기존 기관 방문')
            ->set('sharedSupplyItemId', $this->itemIdByCode('00003'))
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('useDate', '2026-06-12')
            ->set('startTime', '10:00')
            ->set('endTime', '11:00')
            ->set('vehicleUsagePurpose', '허용되지 않는 목적')
            ->set('vehicleOdometerBefore', 1000)
            ->call('save')
            ->assertHasErrors(['vehicleUsagePurpose']);
    }

    public function test_existing_institution_visit_requires_registered_institution_selection(): void
    {
        $this->createInstitutionTables();
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-12')
            ->set('startTime', '10:00')
            ->set('endTime', '11:00')
            ->set('sharedSupplyItemId', $this->itemIdByCode('00003'))
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('vehicleUsagePurpose', '기존 기관 방문')
            ->set('vehicleOdometerBefore', 1000)
            ->call('save')
            ->assertHasErrors(['vehicleInstitutionSkCode']);
    }

    public function test_existing_institution_visit_saves_selected_institution(): void
    {
        $this->createInstitutionTables();

        Institution::query()->create([
            'SKcode' => 'SK-VEHICLE-1',
            'AccountName' => '테스트 유치원',
        ]);

        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-12')
            ->set('startTime', '10:00')
            ->set('endTime', '11:00')
            ->set('sharedSupplyItemId', $this->itemIdByCode('00003'))
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('vehicleUsagePurpose', '기존 기관 방문')
            ->call('selectVehicleInstitution', 'SK-VEHICLE-1')
            ->set('vehicleOdometerBefore', 1000)
            ->call('save')
            ->assertHasNoErrors();

        $supply = SharedSupply::query()->where('title', '[출장 차량배차] 신청 및 예약')->latest('id')->firstOrFail();

        $this->assertDatabaseHas('vehicle_usage_logs', [
            'shared_supply_id' => $supply->id,
            'usage_purpose_name' => '기존 기관 방문',
            'institution_sk_code' => 'SK-VEHICLE-1',
            'remarks' => '테스트 유치원',
        ]);

        $this->assertSame('테스트 유치원', $supply->fresh()->purpose);
    }

    public function test_vehicle_dispatch_all_day_schedule_saves_full_day_times(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-12')
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('isAllDay', true)
            ->set('sharedSupplyItemId', $this->itemIdByCode('00003'))
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('vehicleUsagePurpose', '일반업무')
            ->set('vehicleOdometerBefore', 1000)
            ->call('save')
            ->assertHasNoErrors();

        $supply = SharedSupply::query()->latest('id')->firstOrFail();

        $this->assertSame('2026-06-12 00:00:00', $supply->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-12 23:59:59', $supply->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_vehicle_dispatch_all_day_schedule_loads_checkbox_on_edit(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $day = Carbon::parse('2026-06-15');

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => $day->copy()->startOfDay(),
            'ends_at' => $day->copy()->endOfDay(),
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->assertSet('isAllDay', true);
    }

    public function test_existing_institution_visit_shows_search_field_in_modal(): void
    {
        $this->createInstitutionTables();

        Institution::query()->create([
            'SKcode' => 'SK-VEHICLE-2',
            'AccountName' => '분당 테스트 기관',
        ]);

        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('vehicleUsagePurpose', '기존 기관 방문')
            ->assertSee('방문 기관')
            ->set('vehicleInstitutionKeyword', '분당')
            ->assertSee('분당 테스트 기관')
            ->call('selectVehicleInstitution', 'SK-VEHICLE-2')
            ->assertSet('vehicleInstitutionSkCode', 'SK-VEHICLE-2')
            ->assertSet('vehicleInstitutionName', '분당 테스트 기관')
            ->assertSet('purpose', '분당 테스트 기관');
    }

    public function test_vehicle_log_section_is_shown_in_modal_for_vehicle_booking(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->assertSee('차량 운행 기록');

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-15 09:00'),
            'ends_at' => Carbon::parse('2026-06-15 10:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '수정 모달 확인용',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->assertSee('차량 운행 기록');
    }

    public function test_vehicle_booking_creates_vehicle_usage_log(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-12')
            ->set('startTime', '10:00')
            ->set('endTime', '11:00')
            ->set('sharedSupplyItemId', $this->itemIdByCode('00003'))
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('vehicleUsagePurpose', '일반업무')
            ->set('vehicleOdometerBefore', 123456)
            ->set('vehicleOdometerAfter', 123620)
            ->set('vehicleArrivalFloor', 'B2')
            ->set('vehicleArrivalPillar', 'B')
            ->set('vehicleArrivalNumber', '29')
            ->set('purpose', 'B2 B29 / 창의업유치원')
            ->call('save')
            ->assertHasNoErrors();

        $supply = SharedSupply::query()->where('title', '[출장 차량배차] 신청 및 예약')->latest('id')->firstOrFail();

        $this->assertDatabaseHas('vehicle_usage_logs', [
            'shared_supply_id' => $supply->id,
            'user_id' => $user->id,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 123456,
            'odometer_after' => 123620,
            'distance' => 164,
            'arrival_location' => 'B2 / B29',
            'remarks' => 'B2 B29 / 창의업유치원',
        ]);
    }

    public function test_vehicle_booking_prefills_odometer_before_with_latest_log_value(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $vehicleItemId = $this->itemIdByCode('00003');
        $vehicleName = (string) SharedSupplyItem::query()->whereKey($vehicleItemId)->value('name');

        VehicleUsageLog::query()->create([
            'shared_supply_id' => SharedSupply::query()->create([
                'user_id' => $user->id,
                'starts_at' => Carbon::parse('2026-06-01 09:00'),
                'ends_at' => Carbon::parse('2026-06-01 10:00'),
                'shared_supply_item_id' => $vehicleItemId,
                'shared_supply_label_id' => $this->labelIdByCode('01'),
                'title' => '[출장 차량배차] 신청 및 예약',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->id,
            'user_id' => $user->id,
            'vehicle_name' => $vehicleName,
            'usage_purpose_name' => '기존 운행',
            'odometer_before' => 210000,
            'odometer_after' => 210125,
            'distance' => 125,
            'remarks' => '기존 차량 적요',
            'arrival_location' => '기존 도착지',
            'driven_on' => '2026-06-01',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('sharedSupplyItemId', $vehicleItemId)
            ->assertSet('vehicleOdometerBefore', 210125)
            ->assertSet('vehicleLatestArrivalLocation', '기존 도착지');
    }

    public function test_vehicle_booking_shows_location_from_remarks_when_arrival_column_is_empty(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $vehicleItemId = $this->itemIdByCode('00003');
        $vehicleName = (string) SharedSupplyItem::query()->whereKey($vehicleItemId)->value('name');

        VehicleUsageLog::query()->create([
            'shared_supply_id' => SharedSupply::query()->create([
                'user_id' => $user->id,
                'starts_at' => Carbon::parse('2026-06-02 09:00'),
                'ends_at' => Carbon::parse('2026-06-02 10:00'),
                'shared_supply_item_id' => $vehicleItemId,
                'shared_supply_label_id' => $this->labelIdByCode('01'),
                'title' => '[출장 차량배차] 신청 및 예약',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->id,
            'user_id' => $user->id,
            'vehicle_name' => $vehicleName,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 210000,
            'odometer_after' => 210125,
            'distance' => 125,
            'remarks' => '[excel-schedule:2026/06/02 -3] B2/ B16 이천 어린왕자어린이집',
            'arrival_location' => null,
            'driven_on' => '2026-06-02',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('sharedSupplyItemId', $vehicleItemId)
            ->assertSet('vehicleLatestArrivalLocation', 'B2/ B16 이천 어린왕자어린이집');
    }

    public function test_vehicle_booking_skips_in_progress_trip_without_location_for_reference(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $vehicleItemId = $this->itemIdByCode('00008');
        $vehicleName = (string) SharedSupplyItem::query()->whereKey($vehicleItemId)->value('name');

        VehicleUsageLog::query()->create([
            'shared_supply_id' => SharedSupply::query()->create([
                'user_id' => $user->id,
                'starts_at' => Carbon::parse('2026-06-04 09:00'),
                'ends_at' => Carbon::parse('2026-06-04 13:00'),
                'shared_supply_item_id' => $vehicleItemId,
                'shared_supply_label_id' => $this->labelIdByCode('01'),
                'title' => '[출장 차량배차] 신청 및 예약',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->id,
            'user_id' => $user->id,
            'vehicle_name' => $vehicleName,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56467,
            'odometer_after' => 56592,
            'distance' => 125,
            'remarks' => '완료된 운행',
            'arrival_location' => '광명 올어바웃어린이집',
            'driven_on' => '2026-06-04',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => SharedSupply::query()->create([
                'user_id' => $user->id,
                'starts_at' => Carbon::parse('2026-06-05 08:30'),
                'ends_at' => Carbon::parse('2026-06-05 09:00'),
                'shared_supply_item_id' => $vehicleItemId,
                'shared_supply_label_id' => $this->labelIdByCode('01'),
                'title' => '[출장 차량배차] 신청 및 예약',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->id,
            'user_id' => $user->id,
            'vehicle_name' => $vehicleName,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56592,
            'odometer_after' => null,
            'distance' => null,
            'remarks' => '운행 중',
            'arrival_location' => null,
            'driven_on' => '2026-06-05',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('sharedSupplyItemId', $vehicleItemId)
            ->assertSet('vehicleLatestArrivalLocation', '광명 올어바웃어린이집');
    }

    public function test_new_vehicle_booking_is_blocked_when_latest_trip_has_no_odometer_after(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $vehicleItemId = $this->itemIdByCode('00008');
        $vehicleName = (string) SharedSupplyItem::query()->whereKey($vehicleItemId)->value('name');

        VehicleUsageLog::query()->create([
            'shared_supply_id' => SharedSupply::query()->create([
                'user_id' => $user->id,
                'starts_at' => Carbon::parse('2026-06-04 14:00'),
                'ends_at' => Carbon::parse('2026-06-04 15:00'),
                'shared_supply_item_id' => $vehicleItemId,
                'shared_supply_label_id' => $this->labelIdByCode('01'),
                'title' => '[출장 차량배차] 신청 및 예약',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->id,
            'user_id' => $user->id,
            'vehicle_name' => $vehicleName,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56467,
            'odometer_after' => null,
            'distance' => null,
            'remarks' => '운행 중인 차량',
            'driven_on' => '2026-06-04',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $supplyCountBefore = SharedSupply::query()->count();

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-12')
            ->set('startTime', '10:00')
            ->set('endTime', '11:00')
            ->set('sharedSupplyItemId', $vehicleItemId)
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('vehicleUsagePurpose', '일반업무')
            ->set('vehicleOdometerBefore', 56467)
            ->set('purpose', '신규 예약 시도')
            ->call('save')
            ->assertHasErrors(['sharedSupplyItemId'])
            ->assertDispatched('shared-supply-show-alert', message: '해당 차량은 아직 사용 중입니다. 이전 예약을 취소하거나 사용 완료를 기록해 주세요.');

        $this->assertSame($supplyCountBefore, SharedSupply::query()->count());
    }

    public function test_vehicle_usage_log_is_deleted_with_shared_supply(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-10 09:00'),
            'ends_at' => Carbon::parse('2026-06-10 10:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[차량배차] 신청 및 예약',
            'purpose' => '삭제 테스트',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $vehicleUsageLog = VehicleUsageLog::query()->create([
            'shared_supply_id' => $supply->id,
            'user_id' => $user->id,
            'vehicle_name' => '29구9162 (투싼/경유)',
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 100,
            'odometer_after' => 150,
            'distance' => 50,
            'remarks' => '삭제 확인용',
            'driven_on' => '2026-06-10',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertDatabaseHas('vehicle_usage_logs', ['id' => $vehicleUsageLog->id]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->call('delete');

        $this->assertDatabaseMissing('vehicle_usage_logs', ['id' => $vehicleUsageLog->id]);
    }

    public function test_vehicle_list_shows_pending_post_use_badge_and_remark_hint(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 12:00'));

        $user = User::factory()->create(['is_admin' => false]);

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-12 09:00'),
            'ends_at' => Carbon::parse('2026-06-12 10:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00008'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '광명 올어바웃어린이집',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->set('activeTab', 'basic')
            ->assertSee('예약 및 사용중')
            ->assertSee('광명 올어바웃어린이집')
            ->assertSee('입력 대기: 주행후')
            ->assertDontSee('사용 완료');

        $this->travelBack();
    }

    public function test_vehicle_list_shows_post_use_summary_after_excel_style_import_without_arrival_column(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 12:00'));

        $user = User::factory()->create(['is_admin' => false]);

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-04 08:20'),
            'ends_at' => Carbon::parse('2026-06-04 08:30'),
            'shared_supply_item_id' => $this->itemIdByCode('00008'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '일반업무 / 안양 햇빛유치원 / b2 b41',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => $supply->id,
            'user_id' => $user->id,
            'vehicle_name' => '62노5836 (아반떼/경유)',
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56592,
            'odometer_after' => 56602,
            'distance' => 10,
            'remarks' => '일반업무 / 안양 햇빛유치원 / b2 b41',
            'driven_on' => '2026-06-04',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->set('activeTab', 'basic')
            ->assertSee('10km')
            ->assertSee('주행후 56,602')
            ->assertSee('사용 완료')
            ->assertDontSee('입력 대기: 주행후/도착');

        $this->travelBack();
    }

    public function test_vehicle_list_shows_complete_badge_and_distance_summary(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 12:00'));

        $user = User::factory()->create(['is_admin' => false]);

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-12 11:00'),
            'ends_at' => Carbon::parse('2026-06-12 12:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00008'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '일반업무 / 광명 올어바웃어린이집',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => $supply->id,
            'user_id' => $user->id,
            'vehicle_name' => '62노5836 (아반테/경유)',
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56467,
            'odometer_after' => 56592,
            'distance' => 125,
            'arrival_location' => '광명 올어바웃어린이집',
            'driven_on' => '2026-06-12',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->set('activeTab', 'basic')
            ->assertSee('사용 완료')
            ->assertSee('125km')
            ->assertSee('주행후 56,592')
            ->assertDontSee('예약 및 사용중');

        $this->travelBack();
    }

    public function test_edit_vehicle_schedule_loads_partial_vehicle_usage_log(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $vehicleItemId = $this->itemIdByCode('00008');

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-04 14:00'),
            'ends_at' => Carbon::parse('2026-06-04 15:00'),
            'shared_supply_item_id' => $vehicleItemId,
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => 'B2/ B16 이천 어린왕자어린이집',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => $supply->id,
            'user_id' => $user->id,
            'vehicle_name' => (string) SharedSupplyItem::query()->whereKey($vehicleItemId)->value('name'),
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56467,
            'odometer_after' => null,
            'distance' => null,
            'remarks' => 'B2/ B16 이천 어린왕자어린이집',
            'driven_on' => '2026-06-04',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->assertSet('vehicleUsagePurpose', '일반업무')
            ->assertSet('vehicleOdometerBefore', 56467)
            ->assertSet('vehicleOdometerAfter', null)
            ->assertSet('vehicleArrivalFloor', '')
            ->assertSet('vehicleArrivalPillar', '')
            ->assertSet('vehicleArrivalNumber', '');
    }

    public function test_edit_vehicle_schedule_loads_structured_arrival_location_parts(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $vehicleItemId = $this->itemIdByCode('00008');

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-04 14:00'),
            'ends_at' => Carbon::parse('2026-06-04 15:00'),
            'shared_supply_item_id' => $vehicleItemId,
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => 'B2 B16 / 이천 어린왕자어린이집',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => $supply->id,
            'user_id' => $user->id,
            'vehicle_name' => (string) SharedSupplyItem::query()->whereKey($vehicleItemId)->value('name'),
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56467,
            'odometer_after' => 56500,
            'distance' => 33,
            'arrival_location' => 'B2/ B16',
            'remarks' => 'B2 B16 / 이천 어린왕자어린이집',
            'driven_on' => '2026-06-04',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->assertSet('vehicleArrivalFloor', 'B2')
            ->assertSet('vehicleArrivalPillar', 'B')
            ->assertSet('vehicleArrivalNumber', '16')
            ->assertSet('vehicleArrivalLocationLegacy', '');
    }

    public function test_partial_arrival_location_selection_is_rejected(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-04 14:00'),
            'ends_at' => Carbon::parse('2026-06-04 15:00'),
            'shared_supply_item_id' => $this->itemIdByCode('00008'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '방문 예정',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->set('vehicleArrivalFloor', 'B2')
            ->call('save')
            ->assertHasErrors(['vehicleArrivalFloor']);
    }

    public function test_update_vehicle_schedule_persists_partial_vehicle_fields_without_odometer_after(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $vehicleItemId = $this->itemIdByCode('00008');

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-04 14:00'),
            'ends_at' => Carbon::parse('2026-06-04 15:00'),
            'shared_supply_item_id' => $vehicleItemId,
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '방문 예정',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->set('vehicleUsagePurpose', '일반업무')
            ->set('vehicleOdometerBefore', 56467)
            ->set('purpose', 'B2/ B16 이천 어린왕자어린이집')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_usage_logs', [
            'shared_supply_id' => $supply->id,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56467,
            'odometer_after' => null,
            'remarks' => 'B2/ B16 이천 어린왕자어린이집',
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->assertSet('vehicleUsagePurpose', '일반업무')
            ->assertSet('vehicleOdometerBefore', 56467);
    }

    public function test_edit_vehicle_schedule_without_own_log_does_not_prefill_previous_trip_inputs(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $vehicleItemId = $this->itemIdByCode('00008');
        $vehicleName = (string) SharedSupplyItem::query()->whereKey($vehicleItemId)->value('name');

        $firstSupply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-04 09:00'),
            'ends_at' => Carbon::parse('2026-06-04 13:00'),
            'shared_supply_item_id' => $vehicleItemId,
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '첫 번째 일정',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => $firstSupply->id,
            'user_id' => $user->id,
            'vehicle_name' => $vehicleName,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56467,
            'odometer_after' => 56592,
            'distance' => 125,
            'remarks' => '[excel-schedule:2026/06/02 -3] B2/ B16 이천 어린왕자어린이집',
            'arrival_location' => '이천 어린왕자어린이집',
            'driven_on' => '2026-06-04',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $secondSupply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-05 08:30'),
            'ends_at' => Carbon::parse('2026-06-05 09:00'),
            'shared_supply_item_id' => $vehicleItemId,
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '두 번째 일정',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $secondSupply->id)
            ->assertSet('vehicleUsagePurpose', '')
            ->assertSet('vehicleOdometerBefore', null)
            ->assertSet('vehicleOdometerAfter', null)
            ->assertSet('vehicleArrivalFloor', '')
            ->assertSet('vehicleArrivalPillar', '')
            ->assertSet('vehicleArrivalNumber', '')
            ->assertSet('vehicleLatestArrivalLocation', '이천 어린왕자어린이집');
    }

    public function test_create_vehicle_schedule_after_previous_trip_keeps_input_fields_empty_except_odometer_before(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $vehicleItemId = $this->itemIdByCode('00008');
        $vehicleName = (string) SharedSupplyItem::query()->whereKey($vehicleItemId)->value('name');

        VehicleUsageLog::query()->create([
            'shared_supply_id' => SharedSupply::query()->create([
                'user_id' => $user->id,
                'starts_at' => Carbon::parse('2026-06-04 09:00'),
                'ends_at' => Carbon::parse('2026-06-04 13:00'),
                'shared_supply_item_id' => $vehicleItemId,
                'shared_supply_label_id' => $this->labelIdByCode('01'),
                'title' => '[출장 차량배차] 신청 및 예약',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->id,
            'user_id' => $user->id,
            'vehicle_name' => $vehicleName,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56467,
            'odometer_after' => 56592,
            'distance' => 125,
            'remarks' => '첫 번째 운행 적요',
            'arrival_location' => '첫 번째 도착지',
            'driven_on' => '2026-06-04',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('sharedSupplyItemId', $vehicleItemId)
            ->assertSet('vehicleUsagePurpose', '')
            ->assertSet('vehicleOdometerAfter', null)
            ->assertSet('vehicleArrivalFloor', '')
            ->assertSet('vehicleArrivalPillar', '')
            ->assertSet('vehicleArrivalNumber', '')
            ->assertSet('vehicleOdometerBefore', 56592)
            ->assertSet('vehicleLatestArrivalLocation', '첫 번째 도착지');
    }

    public function test_vehicle_edit_for_co_team_shows_support_prompt_with_institution_action_only(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'team' => 'CO',
        ]);

        $supply = $this->createPendingPostUseVehicleSupply($user);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->set('vehicleOdometerAfter', 100120)
            ->set('purpose', 'CO팀 반납 저장')
            ->call('save')
            ->assertSet('showSupportReportPrompt', true)
            ->assertSet('supportReportPromptTeam', 'co')
            ->assertSee('기관 지원 보고서 작성')
            ->assertDontSee('교사 지원 보고서 작성');
    }

    public function test_vehicle_edit_for_coach_team_shows_support_prompt_with_teacher_action(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'team' => 'COACH',
        ]);

        $supply = $this->createPendingPostUseVehicleSupply($user);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->set('vehicleOdometerAfter', 100120)
            ->set('purpose', 'Coach 반납 저장')
            ->call('save')
            ->assertSet('showSupportReportPrompt', true)
            ->assertSet('supportReportPromptTeam', 'coach')
            ->assertSee('기관 지원 보고서 작성')
            ->assertSee('교사 지원 보고서 작성');
    }

    public function test_vehicle_edit_does_not_show_support_prompt_when_usage_purpose_is_commute(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'team' => 'CO',
        ]);

        $supply = $this->createPendingPostUseVehicleSupply($user, '출퇴근');

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->set('vehicleOdometerAfter', 100120)
            ->call('save')
            ->assertSet('showSupportReportPrompt', false);
    }

    public function test_vehicle_edit_does_not_show_support_prompt_when_usage_purpose_is_non_business(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'team' => 'CO',
        ]);

        $supply = $this->createPendingPostUseVehicleSupply($user, '업무외');

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->set('vehicleOdometerAfter', 100120)
            ->call('save')
            ->assertSet('showSupportReportPrompt', false);
    }

    public function test_vehicle_create_does_not_show_support_prompt_even_with_post_use_odometer(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'team' => 'CO',
        ]);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openCreateModal')
            ->set('useDate', '2026-06-20')
            ->set('startTime', '09:00')
            ->set('endTime', '10:00')
            ->set('sharedSupplyItemId', $this->itemIdByCode('00003'))
            ->set('sharedSupplyLabelId', $this->labelIdByCode('01'))
            ->set('title', '[출장 차량배차] 신청 및 예약')
            ->set('vehicleUsagePurpose', '일반업무')
            ->set('vehicleOdometerBefore', 200000)
            ->set('vehicleOdometerAfter', 200020)
            ->set('purpose', '신규 등록 테스트')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showSupportReportPrompt', false);
    }

    public function test_vehicle_edit_does_not_show_support_prompt_for_cs_team(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'team' => 'CS',
        ]);

        $supply = $this->createPendingPostUseVehicleSupply($user);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->set('vehicleOdometerAfter', 100120)
            ->call('save')
            ->assertSet('showSupportReportPrompt', false);
    }

    public function test_vehicle_edit_skips_support_prompt_when_same_day_support_record_exists(): void
    {
        if (! Schema::hasTable('S_SupportInfo_Account')) {
            $this->markTestSkipped('S_SupportInfo_Account 테이블이 없어 당일 보고서 중복 체크를 검증할 수 없습니다.');
        }

        $user = User::factory()->create([
            'is_admin' => false,
            'team' => 'CO',
            'name' => '홍길동',
        ]);

        $supply = $this->createPendingPostUseVehicleSupply($user, '일반업무', '2026-06-18');

        $payload = [
            'Year' => 2026,
            'SK_Code' => 'SK-EXIST-001',
            'Account_Name' => '테스트 기관',
            'TR_Name' => $user->nameForCoReports(),
            'Support_Date' => '2026-06-18',
            'Meet_Time' => '10:00',
            'Target' => '기존 작성자',
            'Support_Type' => '방문',
            'Issue' => '당일 이미 작성된 보고서',
            'TO_Account' => '테스트',
            'Status' => '완료',
            'dePart' => 'CO',
            'CreatedDate' => now(),
            'CompletedDate' => now(),
        ];

        $filteredPayload = collect($payload)
            ->filter(fn (mixed $value, string $column): bool => SupportRecord::tableHasColumn($column))
            ->all();

        SupportRecord::query()->create($filteredPayload);

        Livewire::actingAs($user)
            ->test(SharedSupplyManager::class)
            ->call('openEditModal', $supply->id)
            ->set('vehicleOdometerAfter', 100120)
            ->call('save')
            ->assertSet('showSupportReportPrompt', false);
    }

    private function createPendingPostUseVehicleSupply(
        User $user,
        string $usagePurpose = '일반업무',
        string $date = '2026-06-12'
    ): SharedSupply {
        $startsAt = Carbon::parse($date.' 09:00');
        $endsAt = Carbon::parse($date.' 10:00');

        $supply = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'shared_supply_item_id' => $this->itemIdByCode('00003'),
            'shared_supply_label_id' => $this->labelIdByCode('01'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '반납 전 상태',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => $supply->id,
            'user_id' => $user->id,
            'vehicle_name' => (string) SharedSupplyItem::query()->whereKey($supply->shared_supply_item_id)->value('name'),
            'usage_purpose_name' => $usagePurpose,
            'odometer_before' => 100000,
            'odometer_after' => null,
            'distance' => null,
            'remarks' => '반납 전 기록',
            'driven_on' => $startsAt->toDateString(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return $supply;
    }

    private function createInstitutionTables(): void
    {
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
            $table->string('Customer_Type', 255)->nullable();
        });
    }
}
