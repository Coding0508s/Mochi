<?php

namespace Tests\Feature;

use App\Models\InstitutionExternalMapping;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportInstitutionExternalMappingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('S_AccountName');
        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });
    }

    public function test_dry_run_does_not_change_database(): void
    {
        $path = $this->writeTsv([
            ['기관명', 'AccountNo', 'SKcode', 'ERP 기관명', 'ERP AccountNo', 'PortalCampusID'],
            ['기관 A', '12345', 'SK1001', 'ERP 기관 A', 'E12345', '87756906-8f33-4fba-b692-893a2137cfb0'],
        ]);

        $exitCode = Artisan::call('institutions:import-external-mappings', [
            'file' => $path,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('institution_external_mappings', 0);
    }

    public function test_import_stores_rows_and_links_institution_by_sk_code(): void
    {
        Schema::table('S_AccountName', function (Blueprint $table): void {
            $table->string('AccountNo', 100)->nullable();
            $table->string('PortalCampusID', 100)->nullable();
        });

        \DB::table('S_AccountName')->insert([
            'SKcode' => 'SK1001',
            'AccountName' => '기존 기관',
            'AccountNo' => null,
            'PortalCampusID' => null,
        ]);

        $path = $this->writeTsv([
            ['기관명', 'AccountNo', 'SKcode', 'ERP 기관명', 'ERP AccountNo', 'PortalCampusID'],
            ['기관 A', '12345', 'SK1001', 'ERP 기관 A', 'E12345', '87756906-8f33-4fba-b692-893a2137cfb0'],
            ['기관 B', '99999A', 'SK1002', 'ERP 기관 B', 'E99999A', ''],
        ]);

        $exitCode = Artisan::call('institutions:import-external-mappings', [
            'file' => $path,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('institution_external_mappings', 2);

        $linked = InstitutionExternalMapping::query()->where('sk_code', 'SK1001')->first();
        $this->assertNotNull($linked);
        $this->assertNotNull($linked->institution_id);

        $unlinked = InstitutionExternalMapping::query()->where('sk_code', 'SK1002')->first();
        $this->assertNotNull($unlinked);
        $this->assertNull($unlinked->institution_id);
        $this->assertNull($unlinked->portal_campus_id);
    }

    public function test_import_is_idempotent_without_update_option(): void
    {
        $path = $this->writeTsv([
            ['기관명', 'AccountNo', 'SKcode', 'ERP 기관명', 'ERP AccountNo', 'PortalCampusID'],
            ['기관 A', '12345', 'SK2001', 'ERP 기관 A', 'E12345', '87756906-8f33-4fba-b692-893a2137cfb0'],
        ]);

        Artisan::call('institutions:import-external-mappings', ['file' => $path]);
        Artisan::call('institutions:import-external-mappings', ['file' => $path]);

        $this->assertDatabaseCount('institution_external_mappings', 1);
    }

    public function test_update_option_updates_existing_row(): void
    {
        InstitutionExternalMapping::query()->create([
            'institution_id' => null,
            'institution_name' => '기관 A',
            'account_no' => '12345',
            'sk_code' => 'SK3001',
            'erp_institution_name' => 'ERP 기관 A',
            'erp_account_no' => 'E12345',
            'portal_campus_id' => null,
        ]);

        $path = $this->writeTsv([
            ['기관명', 'AccountNo', 'SKcode', 'ERP 기관명', 'ERP AccountNo', 'PortalCampusID'],
            ['기관 A 수정', '12345', 'SK3001', 'ERP 기관 A 수정', 'E12345X', '3316f851-9181-484b-91ea-b9405f970b72'],
        ]);

        $exitCode = Artisan::call('institutions:import-external-mappings', [
            'file' => $path,
            '--update' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('institution_external_mappings', [
            'sk_code' => 'SK3001',
            'institution_name' => '기관 A 수정',
            'erp_institution_name' => 'ERP 기관 A 수정',
            'erp_account_no' => 'E12345X',
            'portal_campus_id' => '3316f851-9181-484b-91ea-b9405f970b72',
        ]);
    }

    public function test_fails_when_uuid_is_invalid_and_does_not_save_anything(): void
    {
        $path = $this->writeTsv([
            ['기관명', 'AccountNo', 'SKcode', 'ERP 기관명', 'ERP AccountNo', 'PortalCampusID'],
            ['기관 A', '12345', 'SK4001', 'ERP 기관 A', 'E12345', 'not-uuid'],
        ]);

        $exitCode = Artisan::call('institutions:import-external-mappings', [
            'file' => $path,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('institution_external_mappings', 0);
    }

    public function test_fails_when_sk_code_is_invalid_and_does_not_save_anything(): void
    {
        $path = $this->writeTsv([
            ['기관명', 'AccountNo', 'SKcode', 'ERP 기관명', 'ERP AccountNo', 'PortalCampusID'],
            ['기관 A', '12345', 'INVALID-1', 'ERP 기관 A', 'E12345', '87756906-8f33-4fba-b692-893a2137cfb0'],
        ]);

        $exitCode = Artisan::call('institutions:import-external-mappings', [
            'file' => $path,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('institution_external_mappings', 0);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeTsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'institution-mapping-');
        if ($path === false) {
            throw new \RuntimeException('임시 파일 생성에 실패했습니다.');
        }

        $content = collect($rows)
            ->map(fn (array $row): string => implode("\t", $row))
            ->implode("\n");

        file_put_contents($path, $content);

        return $path;
    }
}
