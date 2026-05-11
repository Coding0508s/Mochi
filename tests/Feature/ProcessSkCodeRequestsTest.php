<?php

namespace Tests\Feature;

use App\Jobs\ProcessSkCodeRequestsJob;
use App\Services\PotentialInstitutionSkCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProcessSkCodeRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAccountTables();
        $this->createSkCodeRequestsTable();
        $this->createExternalAssignmentInboundLogsTable();
    }

    public function test_applies_final_sk_code_with_portal_fields(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'LEAD-100',
            'AccountName' => '계약 완료 기관',
            'PortalCampusID' => null,
            'AccountNo' => null,
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'LEAD-100',
            'Account_Name' => '계약 완료 기관',
            'CO' => null,
            'TR' => null,
            'CS' => null,
        ]);

        DB::table('S_CO_NewTarget')->insert([
            'ID' => 100,
            'AccountCode' => 'LEAD-100',
            'AccountName' => '계약 완료 기관',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 100,
            'institution_name' => '계약 완료 기관',
            'temp_sk_code' => 'LEAD-100',
            'final_sk_code' => 'SK-100',
            'portal_campus_id' => 'CAMPUS-100',
            'account_no' => '123-45-67890',
            'co' => '담당CO',
            'tr' => '담당TR',
            'cs' => '담당CS',
            'status' => 'completed',
            'requested_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-100',
            'PortalCampusID' => 'CAMPUS-100',
            'AccountNo' => '123-45-67890',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-100',
            'CO' => '담당CO',
            'TR' => '담당TR',
            'CS' => '담당CS',
        ]);
        $this->assertDatabaseHas('S_CO_NewTarget', [
            'ID' => 100,
            'AccountCode' => 'SK-100',
        ]);
        $this->assertDatabaseHas('sk_code_requests', [
            'temp_sk_code' => 'LEAD-100',
            'final_sk_code' => 'SK-100',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('external_assignment_inbound_logs', [
            'sk_code' => 'SK-100',
            'status' => 'applied',
        ]);

        $this->assertNotNull(DB::table('sk_code_requests')->where('temp_sk_code', 'LEAD-100')->value('applied_at'));
    }

    public function test_keeps_existing_portal_fields_when_request_values_are_empty(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'LEAD-200',
            'AccountName' => '기존 값 유지 기관',
            'PortalCampusID' => 'OLD-CAMPUS',
            'AccountNo' => 'OLD-ACCOUNT',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'LEAD-200',
            'Account_Name' => '기존 값 유지 기관',
            'CO' => '기존CO',
            'TR' => '기존TR',
            'CS' => '기존CS',
        ]);

        DB::table('S_CO_NewTarget')->insert([
            'ID' => 200,
            'AccountCode' => 'LEAD-200',
            'AccountName' => '기존 값 유지 기관',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 200,
            'institution_name' => '기존 값 유지 기관',
            'temp_sk_code' => 'LEAD-200',
            'final_sk_code' => 'SK-200',
            'portal_campus_id' => ' ',
            'account_no' => null,
            'status' => 'completed',
            'requested_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-200',
            'PortalCampusID' => 'OLD-CAMPUS',
            'AccountNo' => 'OLD-ACCOUNT',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-200',
            'CO' => '기존CO',
            'TR' => '기존TR',
            'CS' => '기존CS',
        ]);
    }

    public function test_reapplies_updated_completed_request_after_applied_at(): void
    {
        $appliedAt = now()->subMinutes(10);
        $updatedAt = now();

        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-300',
            'AccountName' => '재반영 기관',
            'PortalCampusID' => 'OLD-CAMPUS',
            'AccountNo' => 'OLD-ACCOUNT',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-300',
            'Account_Name' => '재반영 기관',
            'CO' => '기존CO',
            'TR' => '기존TR',
            'CS' => '기존CS',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 300,
            'institution_name' => '재반영 기관',
            'temp_sk_code' => 'LEAD-300',
            'final_sk_code' => 'SK-300',
            'portal_campus_id' => 'NEW-CAMPUS',
            'account_no' => 'NEW-ACCOUNT',
            'co' => '새CO',
            'tr' => '새TR',
            'cs' => '새CS',
            'status' => 'completed',
            'requested_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
            'applied_at' => $appliedAt,
            'created_at' => now()->subHour(),
            'updated_at' => $updatedAt,
        ]);

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-300',
            'PortalCampusID' => 'NEW-CAMPUS',
            'AccountNo' => 'NEW-ACCOUNT',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-300',
            'CO' => '새CO',
            'TR' => '새TR',
            'CS' => '새CS',
        ]);

        $newAppliedAt = DB::table('sk_code_requests')->where('final_sk_code', 'SK-300')->value('applied_at');
        $this->assertNotNull($newAppliedAt);
        $this->assertNotEquals($appliedAt->toDateTimeString(), $newAppliedAt);

        $rawBody = json_decode(
            (string) DB::table('external_assignment_inbound_logs')->where('sk_code', 'SK-300')->latest('id')->value('raw_body'),
            true
        );
        $this->assertSame('OLD-CAMPUS', $rawBody['before']['portal_campus_id']);
        $this->assertSame('OLD-ACCOUNT', $rawBody['before']['account_no']);
        $this->assertSame('기존CO', $rawBody['before']['co']);
        $this->assertSame('NEW-CAMPUS', $rawBody['portal_campus_id']);
        $this->assertSame('NEW-ACCOUNT', $rawBody['account_no']);
    }

    public function test_reapplies_updated_institution_name_from_completed_request(): void
    {
        $appliedAt = now()->subMinutes(10);

        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-NAME-300',
            'AccountName' => '기존 기관명',
            'PortalCampusID' => 'OLD-CAMPUS',
            'AccountNo' => 'OLD-ACCOUNT',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-NAME-300',
            'Account_Name' => '기존 기관명',
            'CO' => '기존CO',
            'TR' => '기존TR',
            'CS' => '기존CS',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 301,
            'institution_name' => '상대 플랫폼 수정 기관명',
            'temp_sk_code' => 'LEAD-NAME-300',
            'final_sk_code' => 'SK-NAME-300',
            'portal_campus_id' => null,
            'account_no' => null,
            'status' => 'completed',
            'requested_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
            'applied_at' => $appliedAt,
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-NAME-300',
            'AccountName' => '상대 플랫폼 수정 기관명',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-NAME-300',
            'Account_Name' => '상대 플랫폼 수정 기관명',
            'CO' => '기존CO',
            'TR' => '기존TR',
            'CS' => '기존CS',
        ]);

        $rawBody = json_decode(
            (string) DB::table('external_assignment_inbound_logs')->where('sk_code', 'SK-NAME-300')->latest('id')->value('raw_body'),
            true
        );
        $this->assertSame('기존 기관명', $rawBody['before']['institution_name']);
        $this->assertSame('기존 기관명', $rawBody['before']['account_name']);
        $this->assertSame('상대 플랫폼 수정 기관명', $rawBody['institution_name']);
        $this->assertContains('institution', $rawBody['changed_fields']);
    }

    public function test_applies_institution_name_to_linked_co_new_target(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-600',
            'AccountName' => '이전 마스터명',
            'PortalCampusID' => null,
            'AccountNo' => null,
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-600',
            'Account_Name' => '이전 마스터명',
            'CO' => null,
            'TR' => null,
            'CS' => null,
        ]);

        DB::table('S_CO_NewTarget')->insert([
            'ID' => 600,
            'AccountCode' => 'SK-600',
            'AccountName' => '이전 잠재명',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 600,
            'institution_name' => '잠재·마스터 동기 기관명',
            'temp_sk_code' => 'LEAD-600',
            'final_sk_code' => 'SK-600',
            'portal_campus_id' => null,
            'account_no' => null,
            'status' => 'completed',
            'requested_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-600',
            'AccountName' => '잠재·마스터 동기 기관명',
        ]);
        $this->assertDatabaseHas('S_CO_NewTarget', [
            'ID' => 600,
            'AccountName' => '잠재·마스터 동기 기관명',
        ]);
    }

    public function test_creates_s_account_information_when_missing_and_applying_institution_name(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-NO-ACCOUNT-INFO',
            'AccountName' => '마스터만 있음',
            'PortalCampusID' => null,
            'AccountNo' => null,
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        $this->assertSame(0, (int) DB::table('S_Account_Information')->where('SK_Code', 'SK-NO-ACCOUNT-INFO')->count());

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 701,
            'institution_name' => '담당 테이블 신규 반영명',
            'temp_sk_code' => 'LEAD-701',
            'final_sk_code' => 'SK-NO-ACCOUNT-INFO',
            'portal_campus_id' => null,
            'account_no' => null,
            'status' => 'completed',
            'requested_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-NO-ACCOUNT-INFO',
            'AccountName' => '담당 테이블 신규 반영명',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-NO-ACCOUNT-INFO',
            'Account_Name' => '담당 테이블 신규 반영명',
        ]);
    }

    public function test_reapplies_when_final_institution_exists_even_if_applied_at_is_empty(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-400',
            'AccountName' => '실패 후 재반영 기관',
            'PortalCampusID' => 'OLD-CAMPUS',
            'AccountNo' => 'OLD-ACCOUNT',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-400',
            'Account_Name' => '실패 후 재반영 기관',
            'CO' => '기존CO',
            'TR' => '기존TR',
            'CS' => '기존CS',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 400,
            'institution_name' => '실패 후 재반영 기관',
            'temp_sk_code' => 'LEAD-400',
            'final_sk_code' => 'SK-400',
            'portal_campus_id' => 'NEW-CAMPUS',
            'account_no' => 'NEW-ACCOUNT',
            'co' => '재반영CO',
            'tr' => '재반영TR',
            'cs' => '재반영CS',
            'status' => 'completed',
            'error_message' => '등록 대상 기관 SK가 기관 목록에 없습니다.',
            'requested_at' => now()->subHour(),
            'completed_at' => now(),
            'applied_at' => null,
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-400',
            'PortalCampusID' => 'NEW-CAMPUS',
            'AccountNo' => 'NEW-ACCOUNT',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-400',
            'CO' => '재반영CO',
            'TR' => '재반영TR',
            'CS' => '재반영CS',
        ]);

        $this->assertNotNull(DB::table('sk_code_requests')->where('final_sk_code', 'SK-400')->value('applied_at'));

        $rawBody = json_decode(
            (string) DB::table('external_assignment_inbound_logs')->where('sk_code', 'SK-400')->latest('id')->value('raw_body'),
            true
        );
        $this->assertNull($rawBody['replaces_sk'] ?? null);
    }

    public function test_reapply_does_not_fail_when_portal_values_are_already_synced(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-500',
            'AccountName' => '이미 포털 반영 기관',
            'PortalCampusID' => 'SAME-CAMPUS',
            'AccountNo' => null,
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-500',
            'Account_Name' => '이미 포털 반영 기관',
            'CO' => null,
            'TR' => null,
            'CS' => null,
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 500,
            'institution_name' => '이미 포털 반영 기관',
            'temp_sk_code' => 'LEAD-500',
            'final_sk_code' => 'SK-500',
            'portal_campus_id' => 'SAME-CAMPUS',
            'account_no' => null,
            'co' => '동기화CO',
            'tr' => '동기화TR',
            'cs' => '동기화CS',
            'status' => 'completed',
            'error_message' => '확정 SK 기관에 포털/사업자 정보를 반영하지 못했습니다.',
            'requested_at' => now()->subHour(),
            'completed_at' => null,
            'applied_at' => null,
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-500',
            'CO' => '동기화CO',
            'TR' => '동기화TR',
            'CS' => '동기화CS',
        ]);
        $this->assertNotNull(DB::table('sk_code_requests')->where('final_sk_code', 'SK-500')->value('applied_at'));
        $this->assertNull(DB::table('sk_code_requests')->where('final_sk_code', 'SK-500')->value('error_message'));
    }

    private function createAccountTables(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_GSNumber');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');
        Schema::dropIfExists('S_CO_NewTarget');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->string('PortalCampusID', 100)->nullable();
            $table->string('AccountNo', 100)->nullable();
            $table->unsignedInteger('LS')->default(0);
            $table->unsignedInteger('GS_K')->default(0);
            $table->unsignedInteger('GS_E')->default(0);
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
        });

        Schema::create('S_GSNumber', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKCode', 100)->unique();
            $table->string('AccountName', 255)->nullable();
            $table->string('GSnumber', 100)->nullable();
        });

        Schema::create('S_CO_NewTarget', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('AccountCode', 100)->nullable();
            $table->string('AccountName', 255);
        });
    }

    private function createSkCodeRequestsTable(): void
    {
        if (Schema::hasTable('sk_code_requests')) {
            Schema::table('sk_code_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('sk_code_requests', 'portal_campus_id')) {
                    $table->string('portal_campus_id', 100)->nullable();
                }

                if (! Schema::hasColumn('sk_code_requests', 'account_no')) {
                    $table->string('account_no', 100)->nullable();
                }

                if (! Schema::hasColumn('sk_code_requests', 'co')) {
                    $table->string('co', 255)->nullable();
                }

                if (! Schema::hasColumn('sk_code_requests', 'tr')) {
                    $table->string('tr', 255)->nullable();
                }

                if (! Schema::hasColumn('sk_code_requests', 'cs')) {
                    $table->string('cs', 255)->nullable();
                }
            });

            return;
        }

        Schema::create('sk_code_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('co_new_target_id');
            $table->string('institution_name', 200);
            $table->string('temp_sk_code', 64);
            $table->string('final_sk_code', 64)->nullable();
            $table->string('portal_campus_id', 100)->nullable();
            $table->string('account_no', 100)->nullable();
            $table->string('co', 255)->nullable();
            $table->string('tr', 255)->nullable();
            $table->string('cs', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_sk_code_request_update_wins_when_it_is_newer_than_applied_at(): void
    {
        $appliedAt = now()->subMinutes(10);
        $updatedAt = now();

        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-LAST-WINS-REQ',
            'AccountName' => '우리쪽 이전 기관명',
            'PortalCampusID' => 'LOCAL-CAMPUS',
            'AccountNo' => 'LOCAL-ACCOUNT',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-LAST-WINS-REQ',
            'Account_Name' => '우리쪽 이전 기관명',
            'CO' => 'Local CO',
            'TR' => 'Local TR',
            'CS' => 'Local CS',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 903,
            'institution_name' => '요청쪽 최신 기관명',
            'temp_sk_code' => 'LEAD-903',
            'final_sk_code' => 'SK-LAST-WINS-REQ',
            'portal_campus_id' => 'REQ-CAMPUS',
            'account_no' => 'REQ-ACCOUNT',
            'co' => 'Req CO',
            'tr' => 'Req TR',
            'cs' => 'Req CS',
            'status' => 'completed',
            'requested_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
            'applied_at' => $appliedAt,
            'created_at' => now()->subHour(),
            'updated_at' => $updatedAt,
        ]);

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-LAST-WINS-REQ',
            'AccountName' => '요청쪽 최신 기관명',
            'PortalCampusID' => 'REQ-CAMPUS',
            'AccountNo' => 'REQ-ACCOUNT',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-LAST-WINS-REQ',
            'Account_Name' => '요청쪽 최신 기관명',
            'CO' => 'Req CO',
            'TR' => 'Req TR',
            'CS' => 'Req CS',
        ]);
    }

    public function test_reverse_synced_request_is_not_reapplied_by_job(): void
    {
        $syncedAt = now();

        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-LOCAL-WINS',
            'AccountName' => '우리쪽 최신 기관명',
            'PortalCampusID' => 'LOCAL-CAMPUS',
            'AccountNo' => 'LOCAL-ACCOUNT',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-LOCAL-WINS',
            'Account_Name' => '우리쪽 최신 기관명',
            'CO' => 'Local CO',
            'TR' => 'Local TR',
            'CS' => 'Local CS',
        ]);

        DB::table('sk_code_requests')->insert([
            'co_new_target_id' => 904,
            'institution_name' => '우리쪽 최신 기관명',
            'temp_sk_code' => 'LEAD-904',
            'final_sk_code' => 'SK-LOCAL-WINS',
            'portal_campus_id' => 'LOCAL-CAMPUS',
            'account_no' => 'LOCAL-ACCOUNT',
            'co' => 'Local CO',
            'tr' => 'Local TR',
            'cs' => 'Local CS',
            'status' => 'completed',
            'requested_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
            'applied_at' => $syncedAt,
            'created_at' => now()->subHour(),
            'updated_at' => $syncedAt,
        ]);

        $logCountBefore = DB::table('external_assignment_inbound_logs')->count();

        (new ProcessSkCodeRequestsJob)->handle(app(PotentialInstitutionSkCodeService::class));

        $logCountAfter = DB::table('external_assignment_inbound_logs')->count();
        $this->assertSame($logCountBefore, $logCountAfter);
        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-LOCAL-WINS',
            'AccountName' => '우리쪽 최신 기관명',
        ]);
    }

    private function createExternalAssignmentInboundLogsTable(): void
    {
        if (Schema::hasTable('external_assignment_inbound_logs')) {
            return;
        }

        Schema::create('external_assignment_inbound_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('sk_code', 100);
            $table->string('co')->nullable();
            $table->string('tr')->nullable();
            $table->string('cs')->nullable();
            $table->json('raw_body')->nullable();
            $table->string('status', 20)->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }
}
