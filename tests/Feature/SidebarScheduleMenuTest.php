<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarScheduleMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_menu_is_disabled_in_sidebar_for_non_admin(): void
    {
        $user = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $response = $this->actingAs($user)->get(route('schedules.index'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('<span class="font-medium">일정 관리</span>', $content);
        $this->assertStringContainsString('aria-disabled="true"', $content);
        $this->assertStringContainsString('cursor-not-allowed opacity-50', $content);
        $this->assertStringNotContainsString('href="'.route('schedules.index').'"', $content);
        $this->assertStringNotContainsString('href="'.route('shared-supplies.index').'"', $content);
        $this->assertStringNotContainsString('href="'.route('vehicle-usage-history.index').'"', $content);
    }

    public function test_schedule_menu_is_enabled_in_sidebar_for_admin(): void
    {
        $admin = User::factory()->admin()->create([
            'team' => 'COACH',
        ]);

        $response = $this->actingAs($admin)->get(route('schedules.index'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('href="'.route('schedules.index').'"', $content);
        $this->assertStringContainsString('href="'.route('shared-supplies.index').'"', $content);
        $this->assertStringContainsString('href="'.route('vehicle-usage-history.index').'"', $content);
        $this->assertStringNotContainsString('aria-disabled="true"', $content);
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
