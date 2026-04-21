<?php

namespace Tests\Feature;

use App\Livewire\SalesforceFileManager;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SalesforceFileManagerPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSfAccountTable();
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

    private function createContractDocument(array $attributes = []): int
    {
        $defaults = [
            'sk_code' => 'SK-001',
            'account_name' => '기본기관',
            'changed_account_name' => null,
            'business_number' => null,
            'document_date' => '2026-04-21',
            'document_time' => '10:20:27',
            'consultant' => '담당자A',
            'original_filename' => 'default.pdf',
            'stored_disk' => 'local',
            'stored_path' => 'contract-documents/default.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1234,
            'uploaded_by' => 'tester',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return (int) DB::table('contract_documents')->insertGetId(array_merge($defaults, $attributes));
    }

    public function test_salesforce_file_manager_route_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('salesforce-files.index'))
            ->assertOk()
            ->assertSee('Salesforce 파일 관리')
            ->assertSee('기관 목록')
            ->assertSee('미분류 파일');
    }

    public function test_account_mode_shows_sf_and_contract_only_rows(): void
    {
        $user = User::factory()->create();

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '강남 리틀아이비',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        DB::table('SF_Files')->insert([
            'fileName' => '0015i00000oOSBqAAO_계약서A.pdf',
            'download_Cnt' => 0,
            'LastUpdate_Date' => '2026-04-21',
            'User' => 'sf-user',
            'created_Date' => '2026-04-21',
        ]);

        $linkedDocPath = 'contract-documents/linked-a.pdf';
        Storage::disk('local')->put($linkedDocPath, 'dummy');
        $this->createContractDocument([
            'original_filename' => '0015i00000oOSBqAAO_계약서A.pdf',
            'stored_path' => $linkedDocPath,
        ]);

        $this->createContractDocument([
            'original_filename' => 'a0C5i00000AW7q5EAD_내부파일.pdf',
            'stored_path' => 'contract-documents/contract-only.pdf',
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->assertSee('강남 리틀아이비')
            ->assertSee('0015i00000oOSBqAAO_계약서A.pdf')
            ->assertSee('a0C5i00000AW7q5EAD_내부파일.pdf')
            ->assertSee('Terminated (GTS)');
    }

    public function test_unlinked_tab_lists_parse_failed_and_account_missing_files(): void
    {
        $user = User::factory()->create();

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '강남 리틀아이비',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        DB::table('SF_Files')->insert([
            'fileName' => '파싱불가파일.pdf',
            'download_Cnt' => 0,
            'LastUpdate_Date' => '2026-04-21',
            'User' => 'sf-user',
            'created_Date' => '2026-04-21',
        ]);

        DB::table('SF_Files')->insert([
            'fileName' => '0015i00000UNKNOWN1_미매칭.pdf',
            'download_Cnt' => 0,
            'LastUpdate_Date' => '2026-04-21',
            'User' => 'sf-user',
            'created_Date' => '2026-04-21',
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->call('switchMasterTab', 'unlinked')
            ->assertSee('파싱불가파일.pdf')
            ->assertSee('0015i00000UNKNOWN1_미매칭.pdf')
            ->assertSee('ID 파싱 실패')
            ->assertSee('계정 없음');
    }

    public function test_actions_are_disabled_when_physical_file_is_missing(): void
    {
        $user = User::factory()->create();

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '강남 리틀아이비',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        DB::table('SF_Files')->insert([
            'fileName' => '0015i00000oOSBqAAO_a0C5i00000AW7q5EAD_물리파일없음.pdf',
            'download_Cnt' => 0,
            'LastUpdate_Date' => '2026-04-21',
            'User' => 'sf-user',
            'created_Date' => '2026-04-21',
        ]);

        $this->createContractDocument([
            'original_filename' => '0015i00000oOSBqAAO_a0C5i00000AW7q5EAD_물리파일없음.pdf',
            'stored_path' => 'contract-documents/not-exists.pdf',
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->assertSee('원본 파일 없음')
            ->assertSee('미리보기 불가')
            ->assertSee('다운로드 불가');
    }

    public function test_preview_can_be_opened_and_closed_in_modal(): void
    {
        $user = User::factory()->create();

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '강남 리틀아이비',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        DB::table('SF_Files')->insert([
            'fileName' => '0015i00000oOSBqAAO_modal-test.pdf',
            'download_Cnt' => 0,
            'LastUpdate_Date' => '2026-04-21',
            'User' => 'sf-user',
            'created_Date' => '2026-04-21',
        ]);

        $path = 'contract-documents/modal-test.pdf';
        Storage::disk('local')->put($path, 'dummy');
        $docId = $this->createContractDocument([
            'original_filename' => '0015i00000oOSBqAAO_modal-test.pdf',
            'stored_path' => $path,
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->call('openPreviewModal', $docId)
            ->assertSet('showPreviewModal', true)
            ->assertSet('previewDocId', $docId)
            ->assertSee('닫기')
            ->call('closePreviewModal')
            ->assertSet('showPreviewModal', false);
    }

    public function test_unlinked_file_can_enable_preview_when_contract_filename_is_similar(): void
    {
        $user = User::factory()->create();

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '강남 리틀아이비',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        DB::table('SF_Files')->insert([
            'fileName' => 'not_rule_강남리틀아이비_사업자등록증_20260421.pdf',
            'download_Cnt' => 0,
            'LastUpdate_Date' => '2026-04-21',
            'User' => 'sf-user',
            'created_Date' => '2026-04-21',
        ]);

        $path = 'contract-documents/similar-name.pdf';
        Storage::disk('local')->put($path, 'dummy');
        $this->createContractDocument([
            'original_filename' => '강남리틀아이비 사업자등록증 20260421.pdf',
            'stored_path' => $path,
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->call('switchMasterTab', 'unlinked')
            ->assertSee('다운로드 가능')
            ->assertSee('미리보기')
            ->assertDontSee('미리보기 불가');
    }

    public function test_unlinked_file_with_salesforce_ids_still_matches_short_korean_filename(): void
    {
        $user = User::factory()->create();

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '강남 리틀아이비',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        DB::table('SF_Files')->insert([
            'fileName' => 'a0Y5i000002yUlVEAU_0685i00000CMKnbAAH_화산성민.pdf',
            'download_Cnt' => 0,
            'LastUpdate_Date' => '2026-04-21',
            'User' => 'sf-user',
            'created_Date' => '2026-04-21',
        ]);

        $path = 'contract-documents/short-korean-token.pdf';
        Storage::disk('local')->put($path, 'dummy');
        $this->createContractDocument([
            'original_filename' => '화산성민 유치원 계약서.pdf',
            'stored_path' => $path,
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->call('switchMasterTab', 'unlinked')
            ->assertSee('다운로드 가능')
            ->assertSee('미리보기')
            ->assertDontSee('미리보기 불가');
    }

    public function test_account_tab_sf_row_can_link_with_non_exact_similar_filename(): void
    {
        $user = User::factory()->create();

        DB::table('SF_Account')->insert([
            'ID' => 9991,
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '강남 리틀아이비',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        DB::table('SF_Files')->insert([
            'fileName' => '0015i00000oOSBqAAO_강남리틀아이비_사업자등록증_20260421.pdf',
            'download_Cnt' => 0,
            'LastUpdate_Date' => '2026-04-21',
            'User' => 'sf-user',
            'created_Date' => '2026-04-21',
        ]);

        $path = 'contract-documents/account-fuzzy.pdf';
        Storage::disk('local')->put($path, 'dummy');
        $this->createContractDocument([
            'account_name' => '강남 리틀아이비',
            'original_filename' => '강남 리틀아이비 사업자등록증 20260421.pdf',
            'stored_path' => $path,
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->call('selectAccount', 9991)
            ->assertSee('다운로드 가능')
            ->assertSee('미리보기')
            ->assertDontSee('미리보기 불가');
    }

    public function test_unlinked_file_can_match_when_contract_filename_is_nfd_korean(): void
    {
        $user = User::factory()->create();

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => '강남 리틀아이비',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        DB::table('SF_Files')->insert([
            'fileName' => 'a0Y5i000002yUlVEAU_0685i00000CMKnbAAH_화산성민.pdf',
            'download_Cnt' => 0,
            'LastUpdate_Date' => '2026-04-21',
            'User' => 'sf-user',
            'created_Date' => '2026-04-21',
        ]);

        $path = 'contract-documents/nfd-korean.pdf';
        Storage::disk('local')->put($path, 'dummy');

        $nfdKorean = "\u{1112}\u{116A}\u{1109}\u{1161}\u{11AB}\u{1109}\u{1165}\u{11BC}\u{1106}\u{1175}\u{11AB}";
        $this->createContractDocument([
            'original_filename' => $nfdKorean.' 유치원 계약서.pdf',
            'stored_path' => $path,
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->call('switchMasterTab', 'unlinked')
            ->assertSee('다운로드 가능')
            ->assertSee('미리보기')
            ->assertDontSee('미리보기 불가');
    }

    public function test_document_can_be_edited_with_replacement_file_in_manager(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $oldPath = 'contract-documents/manager-old.pdf';
        Storage::disk('local')->put($oldPath, 'old-file');

        $docId = $this->createContractDocument([
            'sk_code' => 'SK-MANAGER-1',
            'account_name' => '매니저 기관',
            'original_filename' => 'manager-old.pdf',
            'stored_path' => $oldPath,
            'consultant' => '기존 담당',
            'business_number' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->call('openDocumentEditModal', $docId)
            ->set('editBusinessNumber', '555-11-22222')
            ->set('editConsultant', '수정 담당자')
            ->set('editReplacementUpload', UploadedFile::fake()->create('manager-new.pdf', 150, 'application/pdf'))
            ->call('saveDocumentEdit')
            ->assertHasNoErrors();

        $updated = DB::table('contract_documents')->where('id', $docId)->first();
        $this->assertNotNull($updated);
        $this->assertSame('555-11-22222', (string) $updated->business_number);
        $this->assertSame('수정 담당자', (string) $updated->consultant);
        $this->assertSame('manager-new.pdf', (string) $updated->original_filename);
        $this->assertNotSame($oldPath, (string) $updated->stored_path);
        $this->assertFalse(Storage::disk('local')->exists($oldPath));
        $this->assertTrue(Storage::disk('local')->exists((string) $updated->stored_path));
    }

    public function test_document_can_be_deleted_in_manager(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $storedPath = 'contract-documents/manager-delete-target.pdf';
        Storage::disk('local')->put($storedPath, 'delete-me');

        $docId = $this->createContractDocument([
            'sk_code' => 'SK-MANAGER-2',
            'original_filename' => 'manager-delete-target.pdf',
            'stored_path' => $storedPath,
        ]);

        $this->actingAs($user);

        Livewire::test(SalesforceFileManager::class)
            ->call('deleteDocument', $docId)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('contract_documents', ['id' => $docId]);
        $this->assertFalse(Storage::disk('local')->exists($storedPath));
    }
}
