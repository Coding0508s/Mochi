<?php

namespace Tests\Feature;

use App\GsBrochure\Models\Brochure;
use App\GsBrochure\Models\BrochureRequest;
use App\GsBrochure\Models\RequestItem;
use App\GsBrochure\Models\StockHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GsBrochureApiBoundaryAndTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gs_brochure.connection' => 'sqlite']);
    }

    public function test_public_api_surface_remains_accessible_for_guest(): void
    {
        $brochure = Brochure::create([
            'name' => '공개 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 200,
        ]);

        $this->getJson('/api/gs-brochure/brochures')->assertOk();
        $this->getJson('/api/gs-brochure/institutions')->assertOk();

        $createResponse = $this->postJson('/api/gs-brochure/requests', [
            'date' => '2026-04-22',
            'schoolname' => '테스트 기관',
            'address' => '서울시 강남구',
            'phone' => '010-1234-5678',
            'contact_id' => null,
            'contact_name' => null,
            'brochures' => [[
                'brochure' => $brochure->id,
                'brochureName' => '클라이언트 임의명',
                'quantity' => 20,
            ]],
            'invoices' => [],
        ]);

        $createResponse->assertOk();
        $createResponse->assertJsonStructure(['id']);

        $this->getJson('/api/gs-brochure/requests/search?schoolname=테스트')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_authenticated_staff_request_uses_requester_name_as_contact_name(): void
    {
        $staffUser = User::factory()->create([
            'name' => '직원 신청자',
        ]);
        $brochure = Brochure::create([
            'name' => '직원신청 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 200,
        ]);

        $response = $this->actingAs($staffUser)->postJson('/api/gs-brochure/requests', [
            'date' => '2026-04-22',
            'schoolname' => '직원 신청 기관',
            'address' => '서울시 마포구',
            'phone' => '010-1111-2222',
            'contact_id' => null,
            'contact_name' => '임의입력값',
            'brochures' => [[
                'brochure' => $brochure->id,
                'brochureName' => $brochure->name,
                'quantity' => 20,
            ]],
            'invoices' => [],
        ]);

        $response->assertOk();
        $requestId = (int) $response->json('id');

        $this->assertDatabaseHas((new BrochureRequest)->getTable(), [
            'id' => $requestId,
            'contact_name' => '직원 신청자',
        ]);
        $this->assertDatabaseHas((new StockHistory)->getTable(), [
            'brochure_id' => $brochure->id,
            'type' => '출고',
            'contact_name' => '직원 신청자',
            'schoolname' => '직원 신청 기관',
        ]);
    }

    public function test_staff_can_read_and_update_requests_but_admin_only_apis_remain_protected(): void
    {
        $guestRequests = $this->get('/api/gs-brochure/requests');
        $guestRequests->assertStatus(302);
        $guestRequests->assertRedirect(route('login'));

        $guestContacts = $this->get('/api/gs-brochure/contacts');
        $guestContacts->assertStatus(302);
        $guestContacts->assertRedirect(route('login'));

        $guestAdminUsers = $this->get('/api/gs-brochure/admin/users');
        $guestAdminUsers->assertStatus(302);
        $guestAdminUsers->assertRedirect(route('login'));

        $staffUser = User::factory()->create();
        $brochure = Brochure::create([
            'name' => '수정 테스트 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 120,
        ]);
        $request = BrochureRequest::create([
            'date' => '2026-04-22',
            'schoolname' => '수정 전 기관',
            'address' => '서울시 종로구',
            'phone' => '010-2222-3333',
            'contact_id' => null,
            'contact_name' => null,
        ]);
        RequestItem::create([
            'request_id' => $request->id,
            'brochure_id' => $brochure->id,
            'brochure_name' => $brochure->name,
            'quantity' => 10,
        ]);

        $this->actingAs($staffUser)
            ->get('/api/gs-brochure/requests')
            ->assertOk();

        $this->actingAs($staffUser)
            ->get('/api/gs-brochure/contacts')
            ->assertOk();

        $this->actingAs($staffUser)
            ->putJson('/api/gs-brochure/requests/'.$request->id, [
                'date' => '2026-04-23',
                'schoolname' => '수정 후 기관',
                'address' => '서울시 종로구 청계천로',
                'phone' => '010-2222-3333',
                'contact_id' => null,
                'contact_name' => '직원 수정',
                'brochures' => [[
                    'brochure' => $brochure->id,
                    'brochureName' => $brochure->name,
                    'quantity' => 20,
                ]],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas((new BrochureRequest)->getTable(), [
            'id' => $request->id,
            'schoolname' => '수정 후 기관',
            'contact_name' => '직원 수정',
        ]);

        $this->actingAs($staffUser)
            ->get('/api/gs-brochure/admin/users')
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->delete('/api/gs-brochure/requests/'.$request->id)
            ->assertForbidden();

        $adminUser = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);
        $this->actingAs($adminUser)
            ->getJson('/api/gs-brochure/requests')
            ->assertOk();
    }

    public function test_request_store_is_atomic_when_stock_validation_fails(): void
    {
        $okBrochure = Brochure::create([
            'name' => '재고 충분 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 150,
        ]);
        $lowBrochure = Brochure::create([
            'name' => '재고 부족 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 80,
        ]);

        $response = $this->postJson('/api/gs-brochure/requests', [
            'date' => '2026-04-22',
            'schoolname' => '롤백 기관',
            'address' => '서울시 서초구',
            'phone' => '010-0000-0000',
            'contact_id' => null,
            'contact_name' => null,
            'brochures' => [
                ['brochure' => $okBrochure->id, 'brochureName' => '재고 충분 브로셔', 'quantity' => 20],
                ['brochure' => $lowBrochure->id, 'brochureName' => '재고 부족 브로셔', 'quantity' => 10],
            ],
            'invoices' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['brochures']);

        $this->assertDatabaseCount((new BrochureRequest)->getTable(), 0);
        $this->assertDatabaseCount((new RequestItem)->getTable(), 0);
        $this->assertDatabaseCount((new StockHistory)->getTable(), 0);

        $this->assertSame(150, $okBrochure->fresh()->stock_warehouse);
        $this->assertSame(80, $lowBrochure->fresh()->stock_warehouse);
    }

    public function test_request_store_decrements_warehouse_stock_and_creates_history_records(): void
    {
        $brochure = Brochure::create([
            'name' => '재고 차감 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 200,
        ]);

        $response = $this->postJson('/api/gs-brochure/requests', [
            'date' => '2026-04-22',
            'schoolname' => '성공 기관',
            'address' => '서울시 송파구',
            'phone' => '010-9999-9999',
            'contact_id' => null,
            'contact_name' => null,
            'brochures' => [
                ['brochure' => $brochure->id, 'brochureName' => '임의명', 'quantity' => 20],
                ['brochure' => $brochure->id, 'brochureName' => '임의명2', 'quantity' => 10],
            ],
            'invoices' => [],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['id']);

        $requestId = (int) $response->json('id');
        $this->assertDatabaseHas((new BrochureRequest)->getTable(), ['id' => $requestId, 'schoolname' => '성공 기관']);
        $this->assertDatabaseCount((new RequestItem)->getTable(), 2);
        $this->assertDatabaseHas((new RequestItem)->getTable(), [
            'request_id' => $requestId,
            'brochure_name' => '재고 차감 브로셔',
            'quantity' => 20,
        ]);
        $this->assertDatabaseHas((new RequestItem)->getTable(), [
            'request_id' => $requestId,
            'brochure_name' => '재고 차감 브로셔',
            'quantity' => 10,
        ]);

        $this->assertSame(170, $brochure->fresh()->stock_warehouse);
        $this->assertDatabaseHas((new StockHistory)->getTable(), [
            'brochure_id' => $brochure->id,
            'type' => '출고',
            'location' => 'warehouse',
            'quantity' => 30,
            'before_stock' => 200,
            'after_stock' => 170,
            'schoolname' => '성공 기관',
        ]);
    }
}
