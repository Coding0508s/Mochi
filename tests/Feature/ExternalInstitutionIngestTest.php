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
        Config::set('services.external_institutions.enabled', true);
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
            $table->string('PortalCampusID', 100)->nullable();
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

        if (! Schema::hasTable('S_CO_NewTarget')) {
            Schema::create('S_CO_NewTarget', function (Blueprint $table): void {
                $table->increments('ID');
                $table->string('AccountCode', 100)->nullable();
                $table->string('AccountName', 255);
                $table->boolean('IsContract')->default(false);
            });
        }

        if (! Schema::hasTable('S_SupportInfo_Account')) {
            Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
                $table->increments('ID');
                $table->string('SK_Code', 100)->nullable();
                $table->string('Account_Name', 255)->nullable();
            });
        }
    }

    public function test_returns_503_when_token_not_configured(): void
    {
        Config::set('services.external_institutions.bearer_token', '');

        $this->postJson('/api/internal/institutions/SK-X', [
            'institution_name' => 'A',
        ], [
            'Authorization' => 'Bearer x',
        ])->assertStatus(503);
    }

    public function test_returns_503_when_ingest_api_disabled(): void
    {
        Config::set('services.external_institutions.enabled', false);

        $this->postJson('/api/internal/institutions/SK-X', [
            'institution_name' => 'A',
        ], $this->authHeaders())->assertStatus(503);
    }

    public function test_returns_401_without_valid_bearer(): void
    {
        $this->postJson('/api/internal/institutions/SK-X', [
            'institution_name' => 'A',
        ], [
            'Authorization' => 'Bearer wrong',
        ])->assertStatus(401);
    }

    public function test_creates_institution_and_satellites(): void
    {
        $sk = 'SK-INGEST-'.uniqid();

        $this->postJson("/api/internal/institutions/{$sk}", [
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

        $this->assertDatabaseHas('external_assignment_inbound_logs', [
            'sk_code' => $sk,
            'co' => 'CO One',
            'status' => 'applied',
        ]);
    }

    public function test_creates_institution_with_portal_campus_id_and_account_no(): void
    {
        $sk = 'SK-INGEST-PC-'.uniqid();

        $this->postJson("/api/internal/institutions/{$sk}", [
            'institution_name' => '포털·사업자 연동 테스트',
            'portal_campus_id' => 'PORTAL-C-1',
            'account_no' => '987-65-43210',
        ], $this->authHeaders())->assertOk()
            ->assertJson(['ok' => true, 'sk' => $sk, 'created' => true]);

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => '포털·사업자 연동 테스트',
            'PortalCampusID' => 'PORTAL-C-1',
            'AccountNo' => '987-65-43210',
        ]);
    }

    public function test_second_put_updates_without_duplicating(): void
    {
        $sk = 'SK-INGEST-2-'.uniqid();

        $this->postJson("/api/internal/institutions/{$sk}", [
            'institution_name' => '첫 이름',
            'phone' => '010-1111-1111',
        ], $this->authHeaders())->assertJson(['created' => true]);

        $this->postJson("/api/internal/institutions/{$sk}", [
            'phone' => '010-2222-2222',
        ], $this->authHeaders())->assertJson(['created' => false, 'ok' => true]);

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => '첫 이름',
            'Phone' => '010-2222-2222',
        ]);
    }

    public function test_existing_institution_updates_name_from_institution_name_camel_case_alias(): void
    {
        $sk = 'SK-INGEST-CAMEL-'.uniqid();

        DB::table('S_AccountName')->insert([
            'SKcode' => $sk,
            'AccountName' => '이전 기관명',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        $this->postJson("/api/internal/institutions/{$sk}", [
            'institutionName' => '포도씨 킨더거든',
        ], $this->authHeaders())->assertOk()
            ->assertJson(['ok' => true, 'sk' => $sk, 'created' => false]);

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => '포도씨 킨더거든',
        ]);
    }

    public function test_existing_institution_keeps_name_when_empty_institution_name_sent(): void
    {
        $sk = 'SK-INGEST-KEEP-NAME-'.uniqid();

        DB::table('S_AccountName')->insert([
            'SKcode' => $sk,
            'AccountName' => '유지할 기관명',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => $sk,
            'Account_Name' => '유지할 기관명',
            'CO' => 'CO1',
        ]);

        $this->postJson("/api/internal/institutions/{$sk}", [
            'institution_name' => '',
            'co' => 'CO2',
        ], $this->authHeaders())->assertOk();

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => '유지할 기관명',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $sk,
            'Account_Name' => '유지할 기관명',
            'CO' => 'CO2',
        ]);
    }

    public function test_new_institution_requires_institution_name(): void
    {
        $sk = 'SK-INGEST-NEW-'.uniqid();

        $this->postJson("/api/internal/institutions/{$sk}", [
            'co' => 'Only CO',
        ], $this->authHeaders())->assertStatus(422);

        $this->assertDatabaseMissing('external_assignment_inbound_logs', [
            'sk_code' => $sk,
        ]);
    }

    public function test_updates_assignment_for_existing_institution_with_co_tr_cs_only(): void
    {
        $sk = 'SK-ASSIGNMENT-'.uniqid();

        DB::table('S_AccountName')->insert([
            'SKcode' => $sk,
            'AccountName' => '기존 기관',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => $sk,
            'Account_Name' => '기존 기관',
            'CO' => 'Old CO',
            'TR' => 'Old TR',
            'CS' => 'Old CS',
        ]);

        $this->postJson("/api/internal/institutions/{$sk}", [
            'co' => 'New CO',
            'tr' => 'New TR',
            'cs' => 'New CS',
        ], $this->authHeaders())->assertOk()
            ->assertJson(['ok' => true, 'sk' => $sk, 'created' => false]);

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $sk,
            'Account_Name' => '기존 기관',
            'CO' => 'New CO',
            'TR' => 'New TR',
            'CS' => 'New CS',
        ]);

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => '기존 기관',
        ]);

        $this->assertDatabaseHas('external_assignment_inbound_logs', [
            'sk_code' => $sk,
            'co' => 'New CO',
            'tr' => 'New TR',
            'cs' => 'New CS',
            'status' => 'applied',
        ]);
    }

    public function test_repeated_assignment_import_is_idempotent_for_master_data(): void
    {
        $sk = 'SK-IDEMPOTENT-'.uniqid();

        DB::table('S_AccountName')->insert([
            'SKcode' => $sk,
            'AccountName' => '반복 수신 기관',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        $payload = [
            'co' => 'Same CO',
            'tr' => 'Same TR',
            'cs' => 'Same CS',
        ];

        $this->postJson("/api/internal/institutions/{$sk}", $payload, $this->authHeaders())->assertOk();
        $this->postJson("/api/internal/institutions/{$sk}", $payload, $this->authHeaders())->assertOk();

        $this->assertSame(1, DB::table('S_Account_Information')->where('SK_Code', $sk)->count());
        $this->assertSame(2, DB::table('external_assignment_inbound_logs')->where('sk_code', $sk)->count());

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $sk,
            'CO' => 'Same CO',
            'TR' => 'Same TR',
            'CS' => 'Same CS',
        ]);
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

        $this->postJson("/api/internal/institutions/{$sk}", [
            'director' => '원장',
        ], $this->authHeaders())->assertOk();

        $this->assertDatabaseMissing('institution_visibility_overrides', [
            'sk_code' => $sk,
        ]);
    }

    public function test_replaces_temporary_sk_with_confirmed_external_sk(): void
    {
        $oldSk = 'LEAD-'.uniqid();
        $newSk = 'SK-CONFIRMED-'.uniqid();

        DB::table('S_AccountName')->insert([
            'SKcode' => $oldSk,
            'AccountName' => '임시 기관',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => $oldSk,
            'Account_Name' => '임시 기관',
            'CO' => 'Old CO',
        ]);

        DB::table('S_GSNumber')->insert([
            'SKCode' => $oldSk,
            'AccountName' => '임시 기관',
            'GSnumber' => '1.0',
        ]);

        DB::table('S_CO_NewTarget')->insert([
            'Year' => 2026,
            'CreatedDate' => now()->toDateString(),
            'AccountCode' => $oldSk,
            'AccountName' => '임시 기관',
            'Type' => '신규/진행중',
            'Gubun' => '유치원',
            'IsContract' => true,
        ]);

        DB::table('S_SupportInfo_Account')->insert([
            'SK_Code' => $oldSk,
            'Account_Name' => '임시 기관',
        ]);

        $this->postJson("/api/internal/institutions/{$newSk}", [
            'replaces_sk' => $oldSk,
            'institution_name' => '확정 기관',
            'co' => 'New CO',
            'tr' => 'New TR',
            'cs' => 'New CS',
            'gs_no' => '2.0',
        ], $this->authHeaders())->assertOk()
            ->assertJson(['ok' => true, 'sk' => $newSk, 'created' => false]);

        $this->assertDatabaseMissing('S_AccountName', ['SKcode' => $oldSk]);
        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $newSk,
            'AccountName' => '확정 기관',
            'GSno' => '2.0',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => $newSk,
            'Account_Name' => '확정 기관',
            'CO' => 'New CO',
            'TR' => 'New TR',
            'CS' => 'New CS',
        ]);
        $this->assertDatabaseHas('S_GSNumber', [
            'SKCode' => $newSk,
            'GSnumber' => '2.0',
        ]);
        $this->assertDatabaseHas('S_CO_NewTarget', ['AccountCode' => $newSk]);
        $this->assertDatabaseHas('S_SupportInfo_Account', ['SK_Code' => $newSk]);
    }

    public function test_rejects_replaces_sk_when_confirmed_sk_already_exists(): void
    {
        $oldSk = 'LEAD-'.uniqid();
        $newSk = 'SK-EXISTS-'.uniqid();

        DB::table('S_AccountName')->insert([
            'SKcode' => $oldSk,
            'AccountName' => '임시 기관',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);
        DB::table('S_AccountName')->insert([
            'SKcode' => $newSk,
            'AccountName' => '기존 확정 기관',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        $this->postJson("/api/internal/institutions/{$newSk}", [
            'replaces_sk' => $oldSk,
            'co' => 'New CO',
        ], $this->authHeaders())->assertStatus(422);

        $this->assertDatabaseHas('S_AccountName', ['SKcode' => $oldSk]);
        $this->assertDatabaseHas('S_AccountName', ['SKcode' => $newSk]);
    }

    public function test_rejects_missing_replaces_sk_target(): void
    {
        $this->postJson('/api/internal/institutions/SK-MISSING-REPLACE', [
            'replaces_sk' => 'LEAD-NOT-FOUND',
            'co' => 'New CO',
        ], $this->authHeaders())->assertStatus(422);

        $this->assertDatabaseMissing('S_AccountName', [
            'SKcode' => 'SK-MISSING-REPLACE',
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
