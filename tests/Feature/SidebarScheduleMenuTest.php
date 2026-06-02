<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarScheduleMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_menu_is_exposed_as_single_main_sidebar_entry(): void
    {
        $user = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $response = $this->actingAs($user)->get(route('schedules.index'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('href="'.route('schedules.index').'"', $content);
        $this->assertSame(1, substr_count($content, '<span class="font-medium">일정 관리</span>'));
        $this->assertStringNotContainsString('/schedules?team_menu=', $content);
    }
}
