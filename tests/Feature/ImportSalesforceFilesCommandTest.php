<?php

namespace Tests\Feature;

use App\Models\ContractDocument;
use App\Models\Institution;
use App\Models\SalesforceFile;
use App\Support\SalesforceFilesImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportSalesforceFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $rawDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->createSfAccountTable();
        $this->createInstitutionTable();
        $this->rawDirectory = storage_path('app/testing/salesforce-import/raw');
        if (! is_dir($this->rawDirectory)) {
            mkdir($this->rawDirectory, 0777, true);
        }
    }

    private function createInstitutionTable(): void
    {
        Schema::dropIfExists('S_AccountName');
        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->string('PortalAccountName', 255)->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->rawDirectory);

        parent::tearDown();
    }

    private function createSfAccountTable(): void
    {
        Schema::dropIfExists('SF_Account');
        Schema::create('SF_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('account_ID', 100)->nullable();
            $table->string('Name', 255)->nullable();
            $table->string('GSKR_Billing_Address__c', 255)->nullable();
            $table->string('GSKR_Contract__c', 255)->nullable();
            $table->string('GSKR_Gts_Type__c', 255)->nullable();
        });
    }

    public function test_command_imports_file_with_account_id_and_institution_match(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-IMPORT-1',
            'AccountName' => '강남 리틀아이비',
        ]);

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '강남 리틀아이비',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Active',
        ]);

        $basename = '0015i00000oOSBqAAO_0685i00000CMK7YAAX_테스트계약서.pdf';
        file_put_contents($this->rawDirectory.'/'.$basename, 'pdf-content');

        $this->artisan('salesforce:import-files', [
            'directory' => $this->rawDirectory,
            '--no-interaction' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('import가 완료되었습니다.');

        $this->assertDatabaseHas('contract_documents', [
            'sk_code' => 'SK-IMPORT-1',
            'account_name' => '강남 리틀아이비',
            'original_filename' => $basename,
            'uploaded_by' => 'salesforce-import',
        ]);

        $document = ContractDocument::query()->where('original_filename', $basename)->first();
        $this->assertNotNull($document);
        $this->assertTrue(Storage::disk('local')->exists((string) $document->stored_path));

        $this->assertDatabaseHas('SF_Files', [
            'fileName' => $basename,
            'User' => 'salesforce-import',
        ]);
    }

    public function test_command_skips_excluded_directories_and_existing_filenames(): void
    {
        $included = '0015i00000oOSBqAAO_0685i00000CMK7YAAX_included.pdf';
        file_put_contents($this->rawDirectory.'/'.$included, 'included');

        $excludedDir = $this->rawDirectory.'/mochi';
        mkdir($excludedDir, 0777, true);
        file_put_contents($excludedDir.'/skip-me.pdf', 'skip');

        ContractDocument::query()->create([
            'sk_code' => 'SK-EXISTING',
            'account_name' => '-',
            'document_date' => '2026-04-21',
            'document_time' => '00:00:00',
            'original_filename' => $included,
            'stored_disk' => 'local',
            'stored_path' => 'contract-documents/SK-EXISTING/existing.pdf',
        ]);

        $this->artisan('salesforce:import-files', [
            'directory' => $this->rawDirectory,
            '--no-interaction' => true,
        ])
            ->assertSuccessful();

        $this->assertSame(1, ContractDocument::query()->count());
        $this->assertSame(0, SalesforceFile::query()->count());
    }

    public function test_dry_run_does_not_persist_records_or_files(): void
    {
        $basename = 'a0C5i00000AW7q5EAD_0685i00000CMEHsAAP_dry-run.pdf';
        file_put_contents($this->rawDirectory.'/'.$basename, 'dry-run');

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '계약 매칭 기관',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Active',
        ]);

        $this->artisan('salesforce:import-files', [
            'directory' => $this->rawDirectory,
            '--dry-run' => true,
            '--no-interaction' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('dry-run 완료');

        $this->assertSame(0, ContractDocument::query()->count());
        $this->assertSame(0, SalesforceFile::query()->count());
        $this->assertSame(0, count(Storage::disk('local')->allFiles()));
    }

    public function test_importer_uses_fallback_sk_code_when_institution_is_missing(): void
    {
        $basename = '0015i00000UNKNOWN1_0685i00000CMK7YAAX_unlinked.pdf';
        file_put_contents($this->rawDirectory.'/'.$basename, 'unlinked');

        $result = app(SalesforceFilesImporter::class)->importFromDirectory(
            directory: $this->rawDirectory,
            dryRun: false,
            skipExisting: false,
            createSfFiles: true,
        );

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['unlinked_sk_code']);

        $this->assertDatabaseHas('contract_documents', [
            'sk_code' => SalesforceFilesImporter::FALLBACK_SK_CODE,
            'original_filename' => $basename,
        ]);
    }

    public function test_importer_stores_long_original_filenames_with_short_storage_name(): void
    {
        // ext4 등 리눅스 파일시스템은 파일명 한도가 255바이트라 한글(3바이트) 60자로 제한합니다.
        // 원본명(180바이트+)은 여전히 저장명(UUID 40자)보다 충분히 길어 축약 동작을 검증할 수 있습니다.
        $longLabel = str_repeat('가', 60);
        $basename = '0015i00000oOSBqAAO_0685i00000CMK7YAAX_'.$longLabel.'.pdf';
        file_put_contents($this->rawDirectory.'/'.$basename, 'long-name');

        $this->artisan('salesforce:import-files', [
            'directory' => $this->rawDirectory,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $document = ContractDocument::query()->where('original_filename', $basename)->first();
        $this->assertNotNull($document);
        $this->assertTrue(Storage::disk('local')->exists((string) $document->stored_path));
        $this->assertLessThanOrEqual(50, strlen(basename((string) $document->stored_path)));
    }

    public function test_repair_command_restores_missing_physical_files_from_raw(): void
    {
        $basename = '0015i00000oOSBqAAO_0685i00000CMK7YAAX_repair-target.pdf';
        file_put_contents($this->rawDirectory.'/'.$basename, 'repair-me');

        $document = ContractDocument::query()->create([
            'sk_code' => SalesforceFilesImporter::FALLBACK_SK_CODE,
            'account_name' => '-',
            'document_date' => '2026-06-30',
            'document_time' => '00:00:00',
            'original_filename' => $basename,
            'stored_disk' => 'local',
            'stored_path' => 'contract-documents/'.SalesforceFilesImporter::FALLBACK_SK_CODE.'/missing.pdf',
            'uploaded_by' => 'salesforce-import',
        ]);

        $this->artisan('salesforce:repair-import-files', [
            'directory' => $this->rawDirectory,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $document->refresh();
        $this->assertNotSame('contract-documents/'.SalesforceFilesImporter::FALLBACK_SK_CODE.'/missing.pdf', $document->stored_path);
        $this->assertTrue(Storage::disk('local')->exists((string) $document->stored_path));
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
