<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExternalInstitutionIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.external_institutions.bearer_token', 'test-ingest-token');
        $this->createAccountTables();
    }

    private function createAccountTables(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_GSNumber');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->string('EnglishName', 255)->nullable();
            $table->string('PortalAccountName', 255)->nullable();
            $table->string('AccountNo', 100)->nullable();
            $table->string('GSno', 100)->nullable();
            $table->string('Director', 255)->nullable();
            $table->string('Phone', 100)->nullable();
            $table->string('AccountTel', 100)->nullable();
            $table->string('Address', 255)->nullable();
            $table->string('Gubun', 100)->nullable();
            $table->string('Possibility', 20)->nullable();
            $table->unsignedInteger('LS')->default(0);
            $table->unsignedInteger('GS_K')->default(0);
            $table->unsignedInteger('GS_E')->default(0);
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
            $table->string('Affiliate', 255)->nullable();
            $table->string('Address', 255)->nullable();
        });

        Schema::create('S_GSNumber', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKCode', 100)->unique();
            $table->string('AccountName', 255)->nullable();
            $table->string('GSnumber', 100)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
        });
    }

    public function test_returns_503_when_token_not_configured(): void
    {
        Config::set('services.external_institutions.bearer_token', '');

        $this->putJson('/api/internal/institutions/SK-X', [
            'institution_name' => 'A',
        ], [
            'Authorization' => 'Bearer x',
        ])->assertStatus(503);
    }

    public function test_returns_401_without_valid_bearer(): void
    {
        $this->putJson('/api/internal/institutions/SK-X', [
            'institution_name' => 'A',
        ], [
            'Authorization' => 'Bearer wrong',
        ])->assertStatus(401);
    }

    public function test_creates_institution_and_satellites(): void
    {
        $sk = 'SK-INGEST-'.uniqid();

        $this->putJson("/api/internal/institutions/{$sk}", [
            'institution_name' => '연동 테스트 기관',
            'co' => 'CO One',
            'gs_no' => '1.25',
        ], $this->authHeaders())->assertOk()
            ->assertJson(['ok' => true, 'sk' => $sk, 'created' => true]);

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => '연동 테스트 기관',
            'GSno' => '1.25',
        ]);

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $sk,
            'CO' => 'CO One',
        ]);

        $this->assertDatabaseHas('S_GSNumber', [
            'SKCode' => $sk,
            'GSnumber' => '1.25',
        ]);
    }

    public function test_second_put_updates_without_duplicating(): void
    {
        $sk = 'SK-INGEST-2-'.uniqid();

        $this->putJson("/api/internal/institutions/{$sk}", [
            'institution_name' => '첫 이름',
            'phone' => '010-1111-1111',
        ], $this->authHeaders())->assertJson(['created' => true]);

        $this->putJson("/api/internal/institutions/{$sk}", [
            'phone' => '010-2222-2222',
        ], $this->authHeaders())->assertJson(['created' => false, 'ok' => true]);

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => '첫 이름',
            'Phone' => '010-2222-2222',
        ]);
    }

    public function test_new_institution_requires_institution_name(): void
    {
        $sk = 'SK-INGEST-NEW-'.uniqid();

        $this->putJson("/api/internal/institutions/{$sk}", [
            'co' => 'Only CO',
        ], $this->authHeaders())->assertStatus(422);
    }

    public function test_clears_visibility_override_when_config_enabled(): void
    {
        Config::set('features.external_institution_ingest_clears_hidden', true);

        $sk = 'SK-VIS-'.uniqid();

        DB::table('S_AccountName')->insert([
            'SKcode' => $sk,
            'AccountName' => '숨김 후 연동',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('institution_visibility_overrides')->insert([
            'sk_code' => $sk,
            'hidden_reason' => 'uncontracted',
            'hidden_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/api/internal/institutions/{$sk}", [
            'director' => '원장',
        ], $this->authHeaders())->assertOk();

        $this->assertDatabaseMissing('institution_visibility_overrides', [
            'sk_code' => $sk,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer test-ingest-token',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
