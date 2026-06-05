<?php

namespace Tests\Unit;

use App\Models\TeamSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamScheduleOwnedHighlightTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_schedule_uses_created_by_in_team_view(): void
    {
        $schedule = new TeamSchedule([
            'user_id' => 10,
            'created_by' => 20,
            'source_type' => null,
        ]);

        $this->assertTrue($schedule->isOwnedHighlightFor(20, 'team'));
        $this->assertFalse($schedule->isOwnedHighlightFor(10, 'team'));
    }

    public function test_shared_supply_schedule_uses_user_id_in_team_view(): void
    {
        $schedule = new TeamSchedule([
            'user_id' => 10,
            'created_by' => 20,
            'source_type' => TeamSchedule::SOURCE_TYPE_SHARED_SUPPLY,
            'source_id' => 99,
        ]);

        $this->assertTrue($schedule->isOwnedHighlightFor(10, 'team'));
        $this->assertFalse($schedule->isOwnedHighlightFor(20, 'team'));
    }

    public function test_null_source_type_falls_back_to_created_by(): void
    {
        $schedule = new TeamSchedule([
            'user_id' => 10,
            'created_by' => 20,
            'source_type' => null,
        ]);

        $this->assertTrue($schedule->isOwnedHighlightFor(20, 'team'));
        $this->assertFalse($schedule->isOwnedHighlightFor(10, 'team'));
    }

    public function test_mine_view_never_highlights(): void
    {
        $schedule = new TeamSchedule([
            'user_id' => 10,
            'created_by' => 10,
            'source_type' => TeamSchedule::SOURCE_TYPE_SHARED_SUPPLY,
        ]);

        $this->assertFalse($schedule->isOwnedHighlightFor(10, 'mine'));
    }

    public function test_null_viewer_never_highlights(): void
    {
        $schedule = new TeamSchedule([
            'user_id' => 10,
            'created_by' => 10,
        ]);

        $this->assertFalse($schedule->isOwnedHighlightFor(null, 'team'));
    }
}
