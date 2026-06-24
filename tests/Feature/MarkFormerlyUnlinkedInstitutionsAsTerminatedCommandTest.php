<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\InstitutionExternalMapping;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarkFormerlyUnlinkedInstitutionsAsTerminatedCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    private function createTables(): void
    {
        Schema::dropIfExists('institution_external_mappings');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100);
            $table->string('AccountName', 255)->nullable();
            $table->dateTime('FGC_CreateDate')->nullable();
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
        });

        Schema::create('institution_external_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('institution_id')->nullable();
            $table->string('institution_name', 100);
            $table->string('account_no', 32);
            $table->string('sk_code', 20)->unique();
            $table->string('erp_institution_name', 100);
            $table->string('erp_account_no', 32);
            $table->uuid('portal_campus_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_dry_run_reports_targets_without_writing(): void
    {
        $institution = $this->createMappedInstitution('SK-UNLINKED-DRY', null, null);

        $this->artisan('institutions:mark-unlinked-mappings-terminated')
            ->assertSuccessful()
            ->expectsOutputToContain('dry-run');

        $this->assertDatabaseMissing('S_Account_Information', [
            'SK_Code' => 'SK-UNLINKED-DRY',
        ]);

        $this->assertFalse($institution->fresh()->isTerminatedCustomer());
    }

    public function test_apply_creates_account_information_with_terminated_customer_type(): void
    {
        $institution = $this->createMappedInstitution('SK-UNLINKED-APPLY', '미연결 생성 기관', null);

        $this->artisan('institutions:mark-unlinked-mappings-terminated', ['--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-UNLINKED-APPLY',
            'Account_Name' => '미연결 생성 기관',
            'Customer_Type' => '해지',
        ]);

        $this->assertTrue($institution->fresh(['accountInfo'])->isTerminatedCustomer());
    }

    public function test_apply_updates_existing_non_terminated_account_information(): void
    {
        $institution = $this->createMappedInstitution('SK-UNLINKED-UPDATE', '갱신 대상 기관', 'GTS 13 기존');

        $this->artisan('institutions:mark-unlinked-mappings-terminated', ['--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-UNLINKED-UPDATE',
            'Customer_Type' => '해지',
        ]);

        $this->assertTrue($institution->fresh(['accountInfo'])->isTerminatedCustomer());
    }

    public function test_skips_institutions_with_existing_create_date(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-EXISTING',
            'AccountName' => '기존 마스터 기관',
            'FGC_CreateDate' => now(),
        ]);

        $institutionId = (int) DB::table('S_AccountName')->where('SKcode', 'SK-EXISTING')->value('ID');

        InstitutionExternalMapping::query()->create([
            'institution_id' => $institutionId,
            'institution_name' => '기존 마스터 기관',
            'account_no' => 'A-EXISTING',
            'sk_code' => 'SK-EXISTING',
            'erp_institution_name' => 'ERP 기관',
            'erp_account_no' => 'E-EXISTING',
            'portal_campus_id' => null,
        ]);

        $this->artisan('institutions:mark-unlinked-mappings-terminated', ['--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('S_Account_Information', [
            'SK_Code' => 'SK-EXISTING',
        ]);
    }

    public function test_skips_already_terminated_institutions(): void
    {
        $institution = $this->createMappedInstitution('SK-ALREADY-TERM', '이미 해지', 'GTS 16 Conversion 해지');

        $this->artisan('institutions:mark-unlinked-mappings-terminated', ['--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-ALREADY-TERM',
            'Customer_Type' => 'GTS 16 Conversion 해지',
        ]);

        $this->assertTrue($institution->fresh(['accountInfo'])->isTerminatedCustomer());
    }

    private function createMappedInstitution(string $skCode, ?string $mappingName, ?string $customerType): Institution
    {
        $institution = Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => $mappingName ?? '기본 기관명',
            'FGC_CreateDate' => null,
        ]);

        if ($customerType !== null) {
            DB::table('S_Account_Information')->insert([
                'SK_Code' => $skCode,
                'Account_Name' => $mappingName ?? '기본 기관명',
                'Customer_Type' => $customerType,
            ]);
        }

        InstitutionExternalMapping::query()->create([
            'institution_id' => $institution->ID,
            'institution_name' => $mappingName ?? '기본 기관명',
            'account_no' => 'A-'.$skCode,
            'sk_code' => $skCode,
            'erp_institution_name' => 'ERP '.$skCode,
            'erp_account_no' => 'E-'.$skCode,
            'portal_campus_id' => null,
        ]);

        return $institution->fresh(['accountInfo']);
    }
}
