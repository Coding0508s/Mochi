<?php

namespace Tests\Feature;

use App\Actions\UpdateLegacyTeacherSupportReport;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UpdateLegacyTeacherSupportReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    protected function createRequiredTables(): void
    {
        Schema::dropIfExists('S_Support_OnSite');
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function ($table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_Account_Information', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
        });

        Schema::create('Teachers', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 255)->nullable();
            $table->boolean('ClassInOut')->default(true);
        });

        Schema::create('S_SupportInfo_Account', function ($table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->dateTime('Support_Date')->nullable();
            $table->string('Meet_Time', 20)->nullable();
            $table->string('Target', 255)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->text('Issue')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CreatedDate')->nullable();
            $table->timestamp('CompletedDate')->nullable();
        });

        Schema::create('S_Support_OnSite', function ($table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
            $table->text('Other')->nullable();
        });
    }

    public function test_admin_can_update_legacy_onsite_report(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $teacherId = $this->createTeacher('SK001', '홍길동');
        $legacyId = $this->createLegacyOnsiteReport($teacherId, 'Coach A', '초기 메모');

        app(UpdateLegacyTeacherSupportReport::class)->execute(
            'S_Support_OnSite',
            $legacyId,
            $this->onsitePayload('수정 메모', false),
            $admin,
        );

        $this->assertDatabaseHas('S_Support_OnSite', [
            'ID' => $legacyId,
            'Other' => '수정 메모',
            'Status' => '임시',
        ]);
    }

    public function test_rejects_non_author_for_legacy_report(): void
    {
        $other = User::factory()->create(['name' => 'Other Coach', 'team' => 'TR']);
        $this->actingAs($other);

        $teacherId = $this->createTeacher('SK001', '홍길동');
        $legacyId = $this->createLegacyOnsiteReport($teacherId, 'Coach A', '초기 메모');

        $this->expectException(AuthorizationException::class);

        app(UpdateLegacyTeacherSupportReport::class)->execute(
            'S_Support_OnSite',
            $legacyId,
            $this->onsitePayload('거절', false),
            $other,
        );
    }

    public function test_gate_allows_legacy_author_to_update(): void
    {
        $coach = User::factory()->create(['name' => 'Coach A', 'team' => 'TR']);

        $teacherId = $this->createTeacher('SK001', '홍길동');
        $legacyId = $this->createLegacyOnsiteReport($teacherId, 'Coach A', '초기 메모');

        $this->assertTrue(Gate::forUser($coach)->allows(
            'updateTeacherSupportReport',
            ['S_Support_OnSite', $legacyId],
        ));
    }

    public function test_revert_completed_legacy_report_keeps_support_record_in_progress(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $teacherId = $this->createTeacher('SK001', '홍길동');
        $legacyId = $this->createLegacyOnsiteReport($teacherId, 'Coach A', '완료 메모', '완료');

        $supportRecord = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'TR_Name' => 'Coach A',
            'Support_Date' => '2026-05-19',
            'Meet_Time' => '09:00:00',
            'Target' => '홍길동',
            'Support_Type' => 'On-Site',
            'Issue' => '완료 메모',
            'Status' => '완료',
            'CreatedDate' => now(),
            'CompletedDate' => now(),
        ]);

        app(UpdateLegacyTeacherSupportReport::class)->execute(
            'S_Support_OnSite',
            $legacyId,
            $this->onsitePayload('임시로 되돌림', false),
            $admin,
        );

        $this->assertDatabaseHas('S_Support_OnSite', [
            'ID' => $legacyId,
            'Status' => '임시',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'ID' => $supportRecord->ID,
            'Status' => '진행중',
        ]);

        $this->assertNotNull(SupportRecord::query()->find($supportRecord->ID));
    }

    private function createTeacher(string $skCode, string $name): int
    {
        \DB::table('S_AccountName')->insert([
            'SKcode' => $skCode,
            'AccountName' => '기관A',
        ]);

        \DB::table('S_Account_Information')->insert([
            'SK_Code' => $skCode,
            'Account_Name' => '기관A',
            'TR' => 'Coach A',
        ]);

        return (int) \DB::table('Teachers')->insertGetId([
            'SK_Code' => $skCode,
            'Name' => $name,
            'ClassInOut' => true,
        ]);
    }

    private function createLegacyOnsiteReport(
        int $teacherId,
        string $coachName,
        string $otherNotes,
        string $status = '임시',
    ): int {
        return (int) \DB::table('S_Support_OnSite')->insertGetId([
            'TR_Name' => $coachName,
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'Teacher' => '홍길동',
            'TeacherId' => $teacherId,
            'SupportDate' => '2026-05-19 00:00:00',
            'Status' => $status,
            'Other' => $otherNotes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function onsitePayload(string $otherNotes, bool $markCompleted): array
    {
        return [
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '홍길동',
            'support_date' => '2026-05-19',
            'other_notes' => $otherNotes,
            'procedures' => [],
            'strength_areas' => [],
            'growth_areas' => [],
            'mark_completed' => $markCompleted,
        ];
    }
}
