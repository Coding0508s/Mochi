<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TeamMenuContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTeamReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_team_sidebar_blocks_visible_for_coach_user(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('CS Team', false)
            ->assertSee('Coach Team', false)
            ->assertSee('CO Team', false);
    }

    public function test_coach_user_in_cs_menu_context_is_read_only(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach);

        $this->assertTrue(
            TeamMenuContext::isCrossTeamReadOnlyContext($coach, TeamMenuContext::MENU_CS)
        );
        $this->assertTrue(
            TeamMenuContext::hasExpandedReadScope($coach, TeamMenuContext::MENU_CS)
        );
    }

    public function test_coach_user_in_own_coach_menu_can_mutate(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach);
        $this->get('/coach/teacher-support?team_menu=coach');

        $this->assertFalse(
            TeamMenuContext::isCrossTeamReadOnlyContext($coach, TeamMenuContext::MENU_COACH)
        );
    }

    public function test_admin_is_never_cross_team_read_only(): void
    {
        $admin = User::factory()->admin()->create([
            'team' => 'COACH',
        ]);

        $this->actingAs($admin);

        $this->assertFalse(
            TeamMenuContext::isCrossTeamReadOnlyContext($admin, TeamMenuContext::MENU_CS)
        );
    }

    public function test_coach_user_can_view_co_store_pages(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach)
            ->get('/potential-institutions?team_menu=co')
            ->assertOk();
    }

    public function test_cross_team_context_blocks_mutations(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach);

        $this->expectException(AuthorizationException::class);

        TeamMenuContext::abortIfCrossTeamReadOnly($coach, TeamMenuContext::MENU_CS);
    }

    public function test_cross_team_banner_shown_on_profile_page(): void
    {
        $coach = User::factory()->create([
            'team' => 'COACH',
            'is_admin' => false,
        ]);

        $this->actingAs($coach)
            ->get(route('profile.edit', ['team_menu' => TeamMenuContext::MENU_CS]))
            ->assertOk()
            ->assertSee('다른 팀 메뉴에서는 조회만 가능합니다', false);
    }
}
