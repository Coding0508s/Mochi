<?php

namespace Tests\Unit;

use App\Models\SharedSupply;
use App\Models\SharedSupplyItem;
use App\Models\SharedSupplyLabel;
use App\Models\User;
use App\Models\VehicleUsageLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedSupplyVehicleRowDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function createVehicleSupply(array $attributes = []): SharedSupply
    {
        $user = User::factory()->create();

        return SharedSupply::query()->create(array_merge([
            'user_id' => $user->id,
            'starts_at' => Carbon::parse('2026-06-04 09:00'),
            'ends_at' => Carbon::parse('2026-06-04 10:00'),
            'shared_supply_item_id' => (int) SharedSupplyItem::query()->where('code', '00003')->value('id'),
            'shared_supply_label_id' => (int) SharedSupplyLabel::query()->where('code', '01')->value('id'),
            'title' => '[출장 차량배차] 신청 및 예약',
            'purpose' => '광명 올어바웃어린이집',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $attributes));
    }

    public function test_vehicle_row_status_is_pending_when_log_is_missing(): void
    {
        $supply = $this->createVehicleSupply();

        $this->assertSame('pending_post_use', $supply->vehicleRowStatus());
        $this->assertSame('광명 올어바웃어린이집', $supply->vehicleRowPrimaryRemark());
        $this->assertStringContainsString('입력 대기: 주행후', $supply->vehicleRowSecondaryRemark());
        $this->assertStringNotContainsString('입력 대기: 주행후/도착', $supply->vehicleRowSecondaryRemark());
    }

    public function test_vehicle_row_reflects_excel_imported_odometer_even_without_arrival_location_column(): void
    {
        $supply = $this->createVehicleSupply([
            'purpose' => '일반업무 / 안양 햇빛유치원 / b2 b41',
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => $supply->id,
            'user_id' => $supply->user_id,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 56592,
            'odometer_after' => 56602,
            'distance' => 10,
            'arrival_location' => null,
            'remarks' => '일반업무 / 안양 햇빛유치원 / b2 b41',
            'driven_on' => '2026-06-04',
        ]);

        $supply->load('vehicleUsageLog');

        $this->assertSame('complete', $supply->vehicleRowStatus());
        $this->assertStringContainsString('10km', $supply->vehicleRowSecondaryRemark());
        $this->assertStringContainsString('주행후 56,602', $supply->vehicleRowSecondaryRemark());
        $this->assertStringNotContainsString('입력 대기: 주행후/도착', $supply->vehicleRowSecondaryRemark());
    }

    public function test_vehicle_row_shows_partial_pending_hint_when_only_odometer_after_is_missing(): void
    {
        $supply = $this->createVehicleSupply([
            'purpose' => '일반업무 / 광명 올어바웃어린이집',
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => $supply->id,
            'user_id' => $supply->user_id,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 1000,
            'odometer_after' => null,
            'distance' => null,
            'arrival_location' => '광명 올어바웃어린이집',
            'driven_on' => '2026-06-04',
        ]);

        $supply->load('vehicleUsageLog');

        $this->assertSame('pending_post_use', $supply->vehicleRowStatus());
        $this->assertSame('일반업무 · 입력 대기: 주행후', $supply->vehicleRowSecondaryRemark());
    }

    public function test_vehicle_row_status_is_complete_when_post_use_fields_exist(): void
    {
        $supply = $this->createVehicleSupply([
            'purpose' => '일반업무 / 광명 올어바웃어린이집',
        ]);

        VehicleUsageLog::query()->create([
            'shared_supply_id' => $supply->id,
            'user_id' => $supply->user_id,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 1000,
            'odometer_after' => 1125,
            'distance' => 125,
            'arrival_location' => '광명 올어바웃어린이집',
            'driven_on' => '2026-06-04',
        ]);

        $supply->load('vehicleUsageLog');

        $this->assertSame('complete', $supply->vehicleRowStatus());
        $this->assertSame(
            '광명 올어바웃어린이집 / 일반업무 / 광명 올어바웃어린이집',
            $supply->vehicleRowPrimaryRemark(),
        );
        $this->assertStringContainsString('125km', $supply->vehicleRowSecondaryRemark());
        $this->assertStringContainsString('주행후 1,125', $supply->vehicleRowSecondaryRemark());
    }

    public function test_non_vehicle_row_has_no_vehicle_status(): void
    {
        $supply = $this->createVehicleSupply([
            'title' => '[휴가] 연차휴가',
            'purpose' => '개인 일정',
        ]);

        $this->assertNull($supply->vehicleRowStatus());
    }

    public function test_reservation_category_badge_label_for_vehicle_dispatch(): void
    {
        $supply = $this->createVehicleSupply([
            'title' => '[출장 차량배차] 신청 및 예약',
        ]);

        $this->assertSame('차량 배차', $supply->reservationCategoryBadgeLabel());
    }

    public function test_reservation_category_badge_label_for_meeting_room(): void
    {
        $supply = $this->createVehicleSupply([
            'title' => '[회의실] 신청 및 예약 (팀 회의)',
            'purpose' => '팀 회의',
        ]);

        $this->assertSame('회의실', $supply->reservationCategoryBadgeLabel());
    }

    public function test_reservation_category_badge_label_is_null_for_non_reservation_title(): void
    {
        $supply = $this->createVehicleSupply([
            'title' => '[출장] 출장',
        ]);

        $this->assertNull($supply->reservationCategoryBadgeLabel());
    }
}
