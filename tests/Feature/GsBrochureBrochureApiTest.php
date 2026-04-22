<?php

namespace Tests\Feature;

use App\GsBrochure\Models\Brochure;
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
}
