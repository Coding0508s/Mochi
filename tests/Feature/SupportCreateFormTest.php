<?php

namespace Tests\Feature;

use App\Livewire\SupportCreateForm;
use App\Models\CoNewTarget;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SupportCreateFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportTables();
        $this->createSfAccountTable();
    }

    private function createSupportTables(): void
    {
        Schema::dropIfExists('S_CO_NewTarget_Detail');
        Schema::dropIfExists('S_CO_NewTarget');
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->unsignedInteger('potential_target_id')->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->string('Meet_Time', 50)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->string('Target', 255)->nullable();
            $table->text('Issue')->nullable();
            $table->text('TO_Account')->nullable();
            $table->text('TO_Depart')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
            $table->timestamp('CreatedDate')->nullable();
        });

        Schema::create('S_CO_NewTarget', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->date('CreatedDate')->nullable();
            $table->string('AccountManager', 100)->nullable();
            $table->string('AccountCode', 100)->nullable();
            $table->string('AccountName', 150);
            $table->string('Address', 255)->nullable();
            $table->string('Director', 100)->nullable();
            $table->string('Phone', 50)->nullable();
            $table->string('Connected', 100)->nullable();
            $table->string('Type', 100)->nullable();
            $table->string('Gubun', 100)->nullable();
            $table->integer('LS')->default(0);
            $table->integer('GS_K')->default(0);
            $table->integer('GS_E')->default(0);
            $table->integer('Total')->default(0);
            $table->integer('Approaching')->default(0);
            $table->integer('Presenting')->default(0);
            $table->integer('Consulting')->default(0);
            $table->integer('Closing')->default(0);
            $table->integer('DroppedOut')->default(0);
            $table->boolean('IsContract')->default(false);
            $table->date('ContractedDate')->nullable();
            $table->string('Possibility', 20)->nullable();
        });

        Schema::create('S_CO_NewTarget_Detail', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('AccountName', 150);
            $table->string('AccountManager', 100)->nullable();
            $table->date('MeetingDate');
            $table->string('MeetingTime', 20)->nullable();
            $table->string('MeetingTime_End', 20)->nullable();
            $table->text('Description')->nullable();
            $table->string('ConsultingType', 100)->nullable();
            $table->string('Possibility', 20)->nullable();
        });
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

    public function test_selecting_institution_fills_default_templates_when_empty(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-TPL-1',
            'AccountName' => '템플릿 테스트 기관',
        ]);

        $user = User::factory()->create();

        $expectedAccount = config('support_report_defaults.to_account_template');
        $expectedDepart = config('support_report_defaults.to_depart_template');

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-TPL-1')
            ->assertSet('formToAccount', $expectedAccount)
            ->assertSet('formToDepart', $expectedDepart);
    }

    public function test_selecting_institution_does_not_overwrite_existing_content(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-TPL-2',
            'AccountName' => '기존 내용 기관',
        ]);

        $user = User::factory()->create();
        $existing = '이미 작성한 소통 내용';

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->set('formToAccount', $existing)
            ->set('formToDepart', '타부서 기존')
            ->call('selectInstitution', 'SK-TPL-2')
            ->assertSet('formToAccount', $existing)
            ->assertSet('formToDepart', '타부서 기존');
    }

    public function test_save_persists_to_account_and_to_depart(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SAVE-1',
            'AccountName' => '저장 테스트',
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-SAVE-1')
            ->set('formToAccount', '기관 소통 본문')
            ->set('formToDepart', '타부서 공유 본문')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-SAVE-1',
            'TO_Account' => '기관 소통 본문',
            'TO_Depart' => '타부서 공유 본문',
        ]);
    }

    public function test_save_mirrors_to_potential_detail_for_uncontracted_target(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-POT-1',
            'AccountName' => '잠재 기관',
        ]);

        CoNewTarget::query()->create([
            'AccountCode' => 'SK-POT-1',
            'AccountName' => '잠재 기관',
            'AccountManager' => 'CO 담당자',
            'IsContract' => false,
            'Possibility' => 'B',
        ]);

        $user = User::factory()->create(['name' => '테스터']);

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-POT-1', true)
            ->set('formSupportDate', '2026-04-11')
            ->set('formSupportTime', '14:30')
            ->set('formSupportType', '전화')
            ->set('formToAccount', '기관 소통 내용')
            ->set('formToDepart', '타부서 공유 내용')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'AccountName' => '잠재 기관',
            'AccountManager' => 'CO 담당자',
            'MeetingDate' => '2026-04-11 00:00:00',
            'MeetingTime' => '14:30',
            'ConsultingType' => '전화',
            'Possibility' => 'B',
        ]);
    }

    public function test_save_does_not_mirror_to_potential_detail_for_contracted_target(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CONTRACT-1',
            'AccountName' => '계약 완료 기관',
        ]);

        CoNewTarget::query()->create([
            'AccountCode' => 'SK-CONTRACT-1',
            'AccountName' => '계약 완료 기관',
            'AccountManager' => 'CO 담당자',
            'IsContract' => true,
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-CONTRACT-1')
            ->set('formSupportDate', '2026-04-11')
            ->set('formSupportTime', '10:10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('S_CO_NewTarget_Detail', [
            'AccountName' => '계약 완료 기관',
            'MeetingDate' => '2026-04-11',
        ]);
    }

    public function test_save_for_uncontracted_potential_without_sk_records_potential_target_id(): void
    {
        $potential = CoNewTarget::query()->create([
            'AccountCode' => null,
            'AccountName' => '무SK 잠재 기관',
            'AccountManager' => '잠재 담당자',
            'IsContract' => false,
            'Possibility' => 'C',
        ]);

        $user = User::factory()->create(['name' => '테스터']);

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', '', true, (int) $potential->ID)
            ->set('formSupportDate', '2026-04-21')
            ->set('formSupportTime', '15:10')
            ->set('formSupportType', '대면')
            ->set('formToAccount', '무SK 잠재기관 소통')
            ->set('formToDepart', '내부 공유')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'potential_target_id' => (int) $potential->ID,
            'SK_Code' => null,
            'Account_Name' => '무SK 잠재 기관',
            'Support_Type' => '대면',
        ]);

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'AccountName' => '무SK 잠재 기관',
            'AccountManager' => '잠재 담당자',
            'MeetingDate' => '2026-04-21 00:00:00',
            'MeetingTime' => '15:10',
            'ConsultingType' => '대면',
            'Possibility' => 'C',
        ]);
    }

    public function test_save_rejects_sf_upload_for_uncontracted_potential_without_sk(): void
    {
        $potential = CoNewTarget::query()->create([
            'AccountCode' => null,
            'AccountName' => '무SK 파일제한 기관',
            'AccountManager' => '잠재 담당자',
            'IsContract' => false,
            'Possibility' => 'B',
        ]);

        Storage::fake('local');
        $user = User::factory()->create(['name' => '테스터']);
        $upload = UploadedFile::fake()->create('무sk-업로드.pdf', 100, 'application/pdf');

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', '', true, (int) $potential->ID)
            ->set('formSupportDate', '2026-04-22')
            ->set('formSupportTime', '11:20')
            ->set('sfUpload', $upload)
            ->call('save')
            ->assertHasErrors(['sfUpload']);

        $this->assertDatabaseCount('contract_documents', 0);
        $this->assertDatabaseCount('SF_Files', 0);
    }

    public function test_save_with_sf_upload_creates_contract_document_and_sf_file_with_account_prefix(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SF-1',
            'AccountName' => 'SF 업로드 기관',
        ]);

        DB::table('SF_Account')->insert([
            'account_ID' => '0015i00000oOSBqAAO',
            'Name' => 'SF 업로드 기관',
            'GSKR_Billing_Address__c' => '강남구',
            'GSKR_Contract__c' => 'a0C5i00000AW7q5EAD',
            'GSKR_Gts_Type__c' => 'Terminated (GTS)',
        ]);

        Storage::fake('local');
        $user = User::factory()->create(['name' => '업로더']);
        $upload = UploadedFile::fake()->create('지원자료.pdf', 120, 'application/pdf');

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-SF-1')
            ->set('formSupportDate', '2026-04-11')
            ->set('formSupportTime', '10:10')
            ->set('sfUpload', $upload)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-SF-1',
            'Account_Name' => 'SF 업로드 기관',
        ]);

        $document = DB::table('contract_documents')
            ->where('sk_code', 'SK-SF-1')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($document);
        $this->assertSame('지원자료.pdf', $document->original_filename);
        Storage::disk('local')->assertExists((string) $document->stored_path);

        $sfFile = DB::table('SF_Files')
            ->where('fileName', 'like', '%지원자료.pdf')
            ->orderByDesc('ID')
            ->first();
        $this->assertNotNull($sfFile);
        $this->assertStringStartsWith('0015i00000oOSBqAAO_', (string) $sfFile->fileName);
    }

    public function test_save_with_sf_upload_falls_back_to_original_filename_when_account_not_found(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SF-2',
            'AccountName' => '매칭없음 기관',
        ]);

        Storage::fake('local');
        $user = User::factory()->create(['name' => '업로더2']);
        $upload = UploadedFile::fake()->create('원본파일.pdf', 90, 'application/pdf');

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-SF-2')
            ->set('formSupportDate', '2026-04-12')
            ->set('formSupportTime', '11:30')
            ->set('sfUpload', $upload)
            ->call('save')
            ->assertHasNoErrors();

        $sfFile = DB::table('SF_Files')
            ->where('fileName', '원본파일.pdf')
            ->orderByDesc('ID')
            ->first();

        $this->assertNotNull($sfFile);
    }
}
