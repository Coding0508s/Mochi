<?php

namespace Tests\Feature;

use App\GsBrochure\Models\Brochure;
use App\GsBrochure\Models\BrochureRequest;
use App\GsBrochure\Models\RequestItem;
use App\GsBrochure\Models\StockHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GsBrochureBrochureApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // phpunit은 DB_CONNECTION=sqlite(:memory:)인데, .env에 GS_BROCHURE_DB_CONNECTION=mysql 등이 있으면 검증/저장 DB가 어긋납니다.
        config(['gs_brochure.connection' => 'sqlite']);
    }

    public function test_store_brochure_returns_201_or_json_with_id(): void
    {
        $admin = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/gs-brochure/brochures', [
            'name' => '테스트 브로셔 A',
            'stock' => 1,
            'stock_warehouse' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['name' => '테스트 브로셔 A']);
        $this->assertDatabaseHas((new Brochure)->getTable(), ['name' => '테스트 브로셔 A']);
    }

    public function test_store_duplicate_name_returns_422(): void
    {
        $admin = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);

        Brochure::create([
            'name' => 'Dup',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/gs-brochure/brochures', [
            'name' => 'Dup',
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_image_accepts_reasonable_png_under_12mb(): void
    {
        Storage::fake('public');

        $brochure = Brochure::create([
            'name' => 'Img Target',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);
        $admin = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);

        $file = UploadedFile::fake()->image('cover.png', 120, 120);

        $response = $this->actingAs($admin)->post("/api/gs-brochure/brochures/{$brochure->id}/image", [
            'image' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['image_url']);
        $this->assertNotNull($brochure->fresh()->image_url);
    }

    public function test_destroy_soft_deletes_brochure_and_preserves_stock_history(): void
    {
        $admin = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);

        $brochure = Brochure::create([
            'name' => '삭제 가능 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);

        StockHistory::create([
            'type' => '등록',
            'location' => 'hq',
            'date' => '2026-06-24',
            'brochure_id' => $brochure->id,
            'brochure_name' => $brochure->name,
            'quantity' => 0,
            'before_stock' => 0,
            'after_stock' => 0,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/gs-brochure/brochures/{$brochure->id}");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSoftDeleted((new Brochure)->getTable(), ['id' => $brochure->id]);
        $this->assertDatabaseHas((new StockHistory)->getTable(), ['brochure_id' => $brochure->id]);
    }

    public function test_destroy_soft_deletes_even_with_request_items(): void
    {
        $admin = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);

        $brochure = Brochure::create([
            'name' => '발송 이력 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);

        $request = BrochureRequest::create([
            'date' => '2026-06-24',
            'schoolname' => '테스트 학교',
            'address' => '서울',
            'phone' => '010-1234-5678',
        ]);

        RequestItem::create([
            'request_id' => $request->id,
            'brochure_id' => $brochure->id,
            'brochure_name' => $brochure->name,
            'quantity' => 10,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/gs-brochure/brochures/{$brochure->id}");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSoftDeleted((new Brochure)->getTable(), ['id' => $brochure->id]);
        $this->assertDatabaseHas((new RequestItem)->getTable(), [
            'request_id' => $request->id,
            'brochure_name' => $brochure->name,
        ]);
    }

    public function test_index_excludes_soft_deleted_brochures(): void
    {
        $active = Brochure::create([
            'name' => '활성 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);
        $inactive = Brochure::create([
            'name' => '비활성 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);
        $inactive->delete();

        $response = $this->getJson('/api/gs-brochure/brochures');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $active->id, 'name' => '활성 브로셔']);
        $response->assertJsonMissing(['id' => $inactive->id, 'name' => '비활성 브로셔']);
    }

    public function test_inactive_endpoint_lists_trashed_only(): void
    {
        $admin = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);
        $active = Brochure::create([
            'name' => '활성 목록 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);
        $inactive = Brochure::create([
            'name' => '비활성 목록 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);
        $inactive->delete();

        $response = $this->actingAs($admin)->getJson('/api/gs-brochure/brochures/inactive');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $inactive->id, 'name' => '비활성 목록 브로셔']);
        $response->assertJsonMissing(['id' => $active->id, 'name' => '활성 목록 브로셔']);
    }

    public function test_restore_reactivates_brochure(): void
    {
        $admin = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);
        $brochure = Brochure::create([
            'name' => '복구 대상 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);
        $brochure->delete();

        $response = $this->actingAs($admin)->postJson("/api/gs-brochure/brochures/{$brochure->id}/restore");

        $response->assertOk();
        $response->assertJson(['success' => true, 'id' => $brochure->id]);
        $this->assertDatabaseHas((new Brochure)->getTable(), [
            'id' => $brochure->id,
            'deleted_at' => null,
        ]);
    }

    public function test_store_rejects_name_matching_inactive_brochure_with_restore_hint(): void
    {
        $admin = User::factory()->create([
            'is_gs_brochure_admin' => true,
        ]);

        $inactive = Brochure::create([
            'name' => '복구 안내 브로셔',
            'image_url' => null,
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);
        $inactive->delete();

        $response = $this->actingAs($admin)->postJson('/api/gs-brochure/brochures', [
            'name' => '복구 안내 브로셔',
            'stock' => 0,
            'stock_warehouse' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => '같은 이름의 비활성 브로셔가 있습니다. 비활성 목록에서 다시 활성화해 주세요.',
        ]);
    }
}
