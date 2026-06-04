<?php

namespace Tests\Feature;

use App\Models\SharedSupply;
use App\Models\SharedSupplyItem;
use App\Models\SharedSupplyLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillSharedSupplyCalendarSchedulesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_create_team_schedule(): void
    {
        $user = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00003')->firstOrFail();
        $label = SharedSupplyLabel::query()->where('code', '01')->firstOrFail();

        $supply = SharedSupply::withoutEvents(function () use ($user, $item, $label): SharedSupply {
            return SharedSupply::query()->create([
                'user_id' => $user->id,
                'starts_at' => '2026-06-22 09:00:00',
                'ends_at' => '2026-06-22 10:00:00',
                'shared_supply_item_id' => $item->id,
                'shared_supply_label_id' => $label->id,
                'title' => '[차량배차] 신청 및 예약',
                'purpose' => 'dry-run',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });

        $this->artisan('shared-supplies:backfill-calendar')
            ->assertSuccessful();

        $this->assertDatabaseMissing('team_schedules', [
            'source_type' => 'shared_supply',
            'source_id' => $supply->id,
        ]);
    }

    public function test_apply_creates_team_schedule_for_existing_shared_supply(): void
    {
        $user = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00013')->firstOrFail();
        $label = SharedSupplyLabel::query()->where('code', '02')->firstOrFail();

        $supply = SharedSupply::withoutEvents(function () use ($user, $item, $label): SharedSupply {
            return SharedSupply::query()->create([
                'user_id' => $user->id,
                'starts_at' => '2026-06-23 09:00:00',
                'ends_at' => '2026-06-23 10:00:00',
                'shared_supply_item_id' => $item->id,
                'shared_supply_label_id' => $label->id,
                'title' => '[회의실] 신청 및 예약',
                'purpose' => 'apply',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });

        $this->artisan('shared-supplies:backfill-calendar', ['--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('team_schedules', [
            'source_type' => 'shared_supply',
            'source_id' => $supply->id,
            'user_id' => $user->id,
            'title' => '[회의실] 신청 및 예약',
            'type' => 'etc',
            'visibility' => 'team',
        ]);
    }
}
