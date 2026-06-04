<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarScheduleMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_menu_is_exposed_as_main_sidebar_accordion_with_submenus(): void
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
        $this->assertStringContainsString('href="'.route('shared-supplies.index').'"', $content);
        $this->assertStringContainsString('<span class="sidebar-subitem-label">일정 캘린더</span>', $content);
        $this->assertStringContainsString('<span class="sidebar-subitem-label">공용품 관리</span>', $content);
        $this->assertSame(1, substr_count($content, '<span class="font-medium">일정 관리</span>'));
        $this->assertStringNotContainsString('/schedules?team_menu=', $content);
    }

    public function test_shared_supplies_page_is_accessible(): void
    {
        $user = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $response = $this->actingAs($user)->get(route('shared-supplies.index'));
        $response->assertOk();
        $response->assertSee('공용품 관리', false);
    }
}
