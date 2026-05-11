<?php

namespace Tests\Feature;

use App\Actions\UpsertInstitutionFromExternal;
use App\Jobs\PullInstitutionFromPartnerJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PartnerInstitutionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAccountTables();
        $this->createPartnerTable();

        Config::set('database.connections.partner_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        Config::set('services.partner_institutions.enabled', true);
        Config::set('services.partner_institutions.connection', 'sqlite');
        Config::set('services.partner_institutions.table', 'partner_institutions');
        Config::set('services.partner_institutions.primary_key', 'id');
        Config::set('services.partner_institutions.changed_at_column', 'updated_at');
        Config::set('services.partner_institutions.status_column', 'sync_status');
        Config::set('services.partner_institutions.mark_remote_rows', true);
        Config::set('services.partner_institutions.require_sk_with_portal_and_account', true);
    }

    public function test_pulls_partner_table_into_institution_master(): void
    {
        DB::table('partner_institutions')->insert([
            'sk_code' => 'SK-PARTNER-1',
            'institution_name' => '상대 DB 기관',
            'portal_campus_id' => 'CAMPUS-99',
            'account_no' => '123-45-67890',
            'gs_no' => '3.5',
            'co' => 'Partner CO',
            'tr' => 'Partner TR',
            'cs' => 'Partner CS',
            'sync_status' => 'pending',
            'updated_at' => '2026-05-07 15:00:00',
        ]);

        (new PullInstitutionFromPartnerJob)->handle(app(UpsertInstitutionFromExternal::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-PARTNER-1',
            'AccountName' => '상대 DB 기관',
            'PortalCampusID' => 'CAMPUS-99',
            'AccountNo' => '123-45-67890',
            'GSno' => '3.5',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-PARTNER-1',
            'CO' => 'Partner CO',
            'TR' => 'Partner TR',
            'CS' => 'Partner CS',
        ]);
        $this->assertDatabaseHas('partner_institutions', [
            'sk_code' => 'SK-PARTNER-1',
            'sync_status' => 'applied',
        ]);
        $this->assertDatabaseHas('external_assignment_inbound_logs', [
            'sk_code' => 'SK-PARTNER-1',
            'status' => 'applied',
        ]);
    }

    public function test_fails_row_when_portal_campus_id_missing_and_requirement_enabled(): void
    {
        DB::table('partner_institutions')->insert([
            'sk_code' => 'SK-PARTNER-BAD',
            'institution_name' => '포털 ID 누락',
            'portal_campus_id' => null,
            'account_no' => '123-45-67890',
            'sync_status' => 'pending',
            'updated_at' => '2026-05-07 16:00:00',
        ]);

        (new PullInstitutionFromPartnerJob)->handle(app(UpsertInstitutionFromExternal::class));

        $this->assertDatabaseMissing('S_AccountName', [
            'SKcode' => 'SK-PARTNER-BAD',
        ]);
        $this->assertDatabaseHas('partner_institutions', [
            'sk_code' => 'SK-PARTNER-BAD',
            'sync_status' => 'failed',
        ]);
        $this->assertDatabaseHas('external_assignment_inbound_logs', [
            'sk_code' => 'SK-PARTNER-BAD',
            'status' => 'failed',
        ]);
    }

    public function test_applies_partial_row_when_requirement_disabled(): void
    {
        Config::set('services.partner_institutions.require_sk_with_portal_and_account', false);

        DB::table('partner_institutions')->insert([
            'sk_code' => 'SK-PARTNER-PARTIAL',
            'institution_name' => '부분만',
            'portal_campus_id' => null,
            'account_no' => null,
            'gs_no' => '1.0',
            'sync_status' => 'pending',
            'updated_at' => '2026-05-07 17:00:00',
        ]);

        (new PullInstitutionFromPartnerJob)->handle(app(UpsertInstitutionFromExternal::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-PARTNER-PARTIAL',
            'AccountName' => '부분만',
            'PortalCampusID' => null,
            'AccountNo' => null,
        ]);
        $this->assertDatabaseHas('partner_institutions', [
            'sk_code' => 'SK-PARTNER-PARTIAL',
            'sync_status' => 'applied',
        ]);
    }

    public function test_partner_pull_does_not_overwrite_institution_name_when_sync_disabled(): void
    {
        Config::set('services.partner_institutions.sync_institution_name', false);

        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-PARTNER-NAME',
            'AccountName' => 'E-Ordering 반영 기관명',
            'PortalCampusID' => null,
            'AccountNo' => null,
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-PARTNER-NAME',
            'Account_Name' => 'E-Ordering 반영 기관명',
            'CO' => null,
            'TR' => null,
            'CS' => null,
        ]);

        DB::table('partner_institutions')->insert([
            'sk_code' => 'SK-PARTNER-NAME',
            'institution_name' => '상대 DB 구명',
            'portal_campus_id' => 'CAMP-PN',
            'account_no' => 'ACC-PN-1',
            'sync_status' => 'pending',
            'updated_at' => '2026-05-11 14:00:00',
        ]);

        (new PullInstitutionFromPartnerJob)->handle(app(UpsertInstitutionFromExternal::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-PARTNER-NAME',
            'AccountName' => 'E-Ordering 반영 기관명',
            'PortalCampusID' => 'CAMP-PN',
            'AccountNo' => 'ACC-PN-1',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-PARTNER-NAME',
            'Account_Name' => 'E-Ordering 반영 기관명',
        ]);
    }

    private function createAccountTables(): void
    {
        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->string('PortalCampusID', 100)->nullable();
            $table->string('AccountNo', 100)->nullable();
            $table->string('GSno', 100)->nullable();
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
            $table->string('Address', 255)->nullable();
        });

        Schema::create('S_GSNumber', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKCode', 100)->unique();
            $table->string('AccountName', 255)->nullable();
            $table->string('GSnumber', 100)->nullable();
        });
    }

    private function createPartnerTable(): void
    {
        Schema::create('partner_institutions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('sk_code', 100)->nullable();
            $table->string('replaces_sk', 100)->nullable();
            $table->string('institution_name', 255)->nullable();
            $table->string('portal_campus_id', 100)->nullable();
            $table->string('account_no', 100)->nullable();
            $table->string('gs_no', 100)->nullable();
            $table->string('co', 255)->nullable();
            $table->string('tr', 255)->nullable();
            $table->string('cs', 255)->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('updated_at')->nullable();
        });
    }
}
