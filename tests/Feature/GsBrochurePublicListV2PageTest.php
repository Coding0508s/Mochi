<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GsBrochurePublicListV2PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_requestbrochure_list_v2_renders_without_auth(): void
    {
        $response = $this->get('/requestbrochure-list-v2');

        $response->assertOk();
        $response->assertSee('기관명 또는 전화번호', false);
    }

    public function test_guest_is_redirected_to_login_when_accessing_staff_list_view(): void
    {
        $response = $this->get('/co/gs-brochure/request?view=list');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_permission_can_see_staff_list_view(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/co/gs-brochure/request?view=list');

        $response->assertOk();
        $response->assertSee('신청 내역 조회', false);
    }

    public function test_brochure_admin_user_can_see_staff_list_view_from_unified_route(): void
    {
        $user = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);

        $response = $this->actingAs($user)->get('/co/gs-brochure/request?view=list');

        $response->assertOk();
        $response->assertSee('신청 내역 조회', false);
    }

    public function test_legacy_staff_requests_route_redirects_to_staff_list_for_all_authenticated_users(): void
    {
        $normalUser = User::factory()->create();
        $adminUser = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);

        $normalResponse = $this->actingAs($normalUser)->get('/co/gs-brochure/requests');
        $normalResponse->assertRedirect('/co/gs-brochure/request?view=list');

        $adminResponse = $this->actingAs($adminUser)->get('/co/gs-brochure/requests');
        $adminResponse->assertRedirect('/co/gs-brochure/request?view=list');
    }
}
