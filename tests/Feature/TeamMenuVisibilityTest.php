<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_user_can_access_potential_institutions_route(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach)
            ->get('/potential-institutions')
            ->assertOk();
    }

    public function test_coach_user_can_access_store_inventory_route(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach)
            ->get('/store/inventory')
            ->assertOk();
    }

    public function test_co_user_can_access_potential_institutions_route(): void
    {
        $co = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $this->actingAs($co)
            ->get('/potential-institutions')
            ->assertOk();
    }

    public function test_cs_user_can_access_store_sales_route(): void
    {
        $cs = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $this->actingAs($cs)
            ->get('/store/sales')
            ->assertOk();
    }

    public function test_cs_team_sidebar_shows_brochure_request_menu_only(): void
    {
        $cs = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $response = $this->actingAs($cs)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('co/gs-brochure/request?team_menu=cs', false);
        $response->assertSee('sidebar-subitem-label">브로셔 신청<', false);
        $response->assertDontSee('sidebar-subitem-label">신청 내역<', false);
    }

    public function test_coach_team_sidebar_shows_brochure_request_menu_only(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $response = $this->actingAs($coach)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('co/gs-brochure/request?team_menu=coach', false);
        $response->assertSee('sidebar-subitem-label">브로셔 신청<', false);
        $response->assertDontSee('sidebar-subitem-label">신청 내역<', false);
    }

    public function test_cs_user_can_access_brochure_request_and_list_pages(): void
    {
        $cs = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $this->actingAs($cs)
            ->get(route('co.gs-brochure.request', ['team_menu' => 'cs']))
            ->assertOk();

        $this->actingAs($cs)
            ->get(route('co.gs-brochure.request', ['view' => 'list', 'team_menu' => 'cs']))
            ->assertOk();
    }
}
