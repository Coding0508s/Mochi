<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\InstitutionExternalMapping;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstitutionExternalMappingResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('S_AccountName');
        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100);
            $table->string('AccountName', 255);
            $table->string('PortalCampusID', 100)->nullable();
            $table->string('AccountNo', 100)->nullable();
        });
    }

    public function test_resolved_portal_campus_id_falls_back_to_external_mapping(): void
    {
        $institution = Institution::query()->create([
            'SKcode' => 'SK-FALLBACK-1',
            'AccountName' => '테스트 기관',
            'PortalCampusID' => null,
            'AccountNo' => null,
        ]);

        InstitutionExternalMapping::query()->create([
            'institution_id' => (int) $institution->ID,
            'institution_name' => '테스트 기관',
            'account_no' => '1208005107',
            'sk_code' => 'SK-FALLBACK-1',
            'erp_institution_name' => 'ERP 테스트 기관',
            'erp_account_no' => '1208005107',
            'portal_campus_id' => 'aec17993-033d-414d-a5a4-95ea20430fce',
        ]);

        $resolved = Institution::query()->with('externalMapping')->findOrFail($institution->ID);

        $this->assertSame('aec17993-033d-414d-a5a4-95ea20430fce', $resolved->resolvedPortalCampusId());
        $this->assertSame('1208005107', $resolved->resolvedAccountNo());
    }

    public function test_resolved_values_prioritize_master_columns(): void
    {
        $institution = Institution::query()->create([
            'SKcode' => 'SK-FALLBACK-2',
            'AccountName' => '테스트 기관2',
            'PortalCampusID' => 'MASTER-PORTAL',
            'AccountNo' => 'MASTER-ACCOUNT',
        ]);

        InstitutionExternalMapping::query()->create([
            'institution_id' => (int) $institution->ID,
            'institution_name' => '테스트 기관2',
            'account_no' => 'MAPPING-ACCOUNT',
            'sk_code' => 'SK-FALLBACK-2',
            'erp_institution_name' => 'ERP 테스트 기관2',
            'erp_account_no' => 'MAPPING-ACCOUNT',
            'portal_campus_id' => '3316f851-9181-484b-91ea-b9405f970b72',
        ]);

        $resolved = Institution::query()->with('externalMapping')->findOrFail($institution->ID);

        $this->assertSame('MASTER-PORTAL', $resolved->resolvedPortalCampusId());
        $this->assertSame('MASTER-ACCOUNT', $resolved->resolvedAccountNo());
    }

    public function test_resolved_portal_campus_id_uses_sk_code_mapping_when_institution_id_differs(): void
    {
        $institution = Institution::query()->create([
            'SKcode' => 'SK-DUPLICATED',
            'AccountName' => '중복 SK 기관 A',
            'PortalCampusID' => null,
            'AccountNo' => null,
        ]);

        $otherInstitution = Institution::query()->create([
            'SKcode' => 'SK-DUPLICATED',
            'AccountName' => '중복 SK 기관 B',
            'PortalCampusID' => null,
            'AccountNo' => null,
        ]);

        InstitutionExternalMapping::query()->create([
            'institution_id' => (int) $otherInstitution->ID,
            'institution_name' => '중복 SK 기관 B',
            'account_no' => '1208005107',
            'sk_code' => 'SK-DUPLICATED',
            'erp_institution_name' => 'ERP 중복기관',
            'erp_account_no' => '1208005107',
            'portal_campus_id' => 'aec17993-033d-414d-a5a4-95ea20430fce',
        ]);

        $resolved = Institution::query()->with('externalMappingBySkCode')->findOrFail($institution->ID);

        $this->assertSame('aec17993-033d-414d-a5a4-95ea20430fce', $resolved->resolvedPortalCampusId());
        $this->assertSame('1208005107', $resolved->resolvedAccountNo());
    }
}
