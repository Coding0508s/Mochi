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
}
