<?php

namespace Tests\Feature;

use App\Models\SharedSupply;
use App\Models\SharedSupplyItem;
use App\Models\SharedSupplyLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillSharedSupplyLabelIdsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_update_label_ids(): void
    {
        $user = User::factory()->create();
        $meetingItem = SharedSupplyItem::query()->where('code', '00014')->firstOrFail();

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'shared_supply_item_id' => $meetingItem->id,
            'shared_supply_label_id' => null,
            'title' => '미팅',
            'purpose' => 'dry-run',
            'label' => '회의실',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->artisan('shared-supplies:backfill-label-ids')
            ->assertSuccessful();

        $this->assertDatabaseHas('shared_supplies', [
            'shared_supply_item_id' => $meetingItem->id,
            'shared_supply_label_id' => null,
            'label' => '회의실',
        ]);
    }

    public function test_apply_updates_missing_label_id_using_legacy_and_item_rule(): void
    {
        $user = User::factory()->create();
        $meetingItem = SharedSupplyItem::query()->where('code', '00013')->firstOrFail();
        $vehicleItem = SharedSupplyItem::query()->where('code', '00003')->firstOrFail();

        $meetingLabelId = (int) SharedSupplyLabel::query()->where('code', '02')->value('id');
        $vehicleLabelId = (int) SharedSupplyLabel::query()->where('code', '01')->value('id');

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => '2026-06-21 09:00:00',
            'ends_at' => '2026-06-21 10:00:00',
            'shared_supply_item_id' => $meetingItem->id,
            'shared_supply_label_id' => null,
            'title' => '라벨 문자열 우선',
            'purpose' => '회의실 라벨',
            'label' => '회의실',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => '2026-06-21 11:00:00',
            'ends_at' => '2026-06-21 12:00:00',
            'shared_supply_item_id' => $vehicleItem->id,
            'shared_supply_label_id' => null,
            'title' => '아이템 규칙',
            'purpose' => '차량배차',
            'label' => '',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->artisan('shared-supplies:backfill-label-ids', ['--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('shared_supplies', [
            'shared_supply_item_id' => $meetingItem->id,
            'shared_supply_label_id' => $meetingLabelId,
        ]);
        $this->assertDatabaseHas('shared_supplies', [
            'shared_supply_item_id' => $vehicleItem->id,
            'shared_supply_label_id' => $vehicleLabelId,
        ]);
    }
}
