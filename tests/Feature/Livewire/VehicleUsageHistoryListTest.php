<?php

namespace Tests\Feature\Livewire;

use App\Livewire\VehicleUsageHistoryList;
use App\Models\SharedSupply;
use App\Models\SharedSupplyItem;
use App\Models\User;
use App\Models\VehicleUsageLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleUsageHistoryListTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_successfully(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(VehicleUsageHistoryList::class)
            ->assertStatus(200);
    }

    public function test_filters_by_vehicle(): void
    {
        $user = User::factory()->create();
        $item1 = SharedSupplyItem::create(['name' => '12가3456', 'code' => '001', 'is_active' => true, 'sort_order' => 1]);
        $item2 = SharedSupplyItem::create(['name' => '34나5678', 'code' => '002', 'is_active' => true, 'sort_order' => 2]);

        $supply1 = SharedSupply::create(['user_id' => $user->id, 'shared_supply_item_id' => $item1->id, 'title' => 'test', 'starts_at' => now(), 'ends_at' => now()]);
        $supply2 = SharedSupply::create(['user_id' => $user->id, 'shared_supply_item_id' => $item2->id, 'title' => 'test', 'starts_at' => now(), 'ends_at' => now()]);

        VehicleUsageLog::factory()->create([
            'shared_supply_id' => $supply1->id,
            'vehicle_name' => '12가3456',
            'driven_on' => now(),
        ]);

        VehicleUsageLog::factory()->create([
            'shared_supply_id' => $supply2->id,
            'vehicle_name' => '34나5678',
            'driven_on' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(VehicleUsageHistoryList::class)
            ->set('selectedVehicle', '12가3456')
            ->assertSee('12가3456')
            ->assertDontSeeHtml('<td>34나5678</td>');
    }

    public function test_filters_by_date_range(): void
    {
        $user = User::factory()->create();

        VehicleUsageLog::factory()->create([
            'vehicle_name' => '12가3456',
            'driven_on' => now()->subDays(10),
            'distance' => 100,
        ]);

        VehicleUsageLog::factory()->create([
            'vehicle_name' => '34나5678',
            'driven_on' => now()->subMonths(2),
            'distance' => 200,
        ]);

        Livewire::actingAs($user)
            ->test(VehicleUsageHistoryList::class)
            ->set('dateFrom', now()->subDays(15)->format('Y-m-d'))
            ->set('dateTo', now()->format('Y-m-d'))
            ->assertSee('12가3456')
            ->assertDontSee('34나5678');
    }

    public function test_calculates_summary_metrics(): void
    {
        $user = User::factory()->create();
        $item = SharedSupplyItem::create(['name' => '12가3456', 'code' => '001', 'is_active' => true, 'sort_order' => 1]);

        $supply1 = SharedSupply::create([
            'user_id' => $user->id,
            'shared_supply_item_id' => $item->id,
            'title' => 'test',
            'starts_at' => now()->subDays(2)->setHour(10)->setMinute(0),
            'ends_at' => now()->subDays(2)->setHour(12)->setMinute(0), // 120 mins
        ]);

        $supply2 = SharedSupply::create([
            'user_id' => $user->id,
            'shared_supply_item_id' => $item->id,
            'title' => 'test',
            'starts_at' => now()->subDays(1)->setHour(14)->setMinute(0),
            'ends_at' => now()->subDays(1)->setHour(15)->setMinute(30), // 90 mins
        ]);

        VehicleUsageLog::factory()->create([
            'shared_supply_id' => $supply1->id,
            'distance' => 50,
            'driven_on' => now()->subDays(2),
        ]);

        VehicleUsageLog::factory()->create([
            'shared_supply_id' => $supply2->id,
            'distance' => 30,
            'driven_on' => now()->subDays(1),
        ]);

        Livewire::actingAs($user)
            ->test(VehicleUsageHistoryList::class)
            ->set('dateFrom', now()->subDays(5)->format('Y-m-d'))
            ->set('dateTo', now()->format('Y-m-d'))
            ->assertSee('총 2건')
            ->assertSee('80km')
            ->assertSee('3시간 30분'); // 210 mins = 3h 30m
    }

    public function test_exports_to_excel(): void
    {
        $user = User::factory()->create();

        VehicleUsageLog::factory()->create([
            'vehicle_name' => '12가3456',
            'driven_on' => now(),
        ]);

        $now = now();
        Carbon::setTestNow($now);

        Livewire::actingAs($user)
            ->test(VehicleUsageHistoryList::class)
            ->call('exportToExcel')
            ->assertFileDownloaded('차량_사용_내역_'.$now->format('Ymd_His').'.xlsx');
    }

    public function test_shows_empty_state_when_no_data(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(VehicleUsageHistoryList::class)
            ->assertSee('조회된 사용 내역이 없습니다');
    }
}
