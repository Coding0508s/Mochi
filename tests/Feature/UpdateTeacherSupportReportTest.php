<?php

namespace Tests\Feature;

use App\Actions\UpdateTeacherSupportReport;
use App\Models\SupportRecord;
use App\Models\TeacherOnsiteSupportReport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UpdateTeacherSupportReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    protected function createRequiredTables(): void
    {
        Schema::dropIfExists('teacher_onsite_support_reports');
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

        Schema::create('teacher_onsite_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->text('other_notes')->nullable();
            $table->json('procedures')->nullable();
            $table->json('strength_areas')->nullable();
            $table->json('growth_areas')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_admin_can_update_mochi_onsite_report(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $teacherId = $this->createTeacher('SK001', '홍길동');
        $report = $this->createOnsiteReport($teacherId, $admin->id, 'Coach A');

        app(UpdateTeacherSupportReport::class)->execute(
            'teacher_onsite_support_reports',
            (int) $report->id,
            $this->onsitePayload('수정 메모', false),
            $admin,
        );

        $this->assertDatabaseHas('teacher_onsite_support_reports', [
            'id' => $report->id,
            'other_notes' => '수정 메모',
            'status' => '임시',
        ]);
    }

    public function test_rejects_non_author(): void
    {
        $author = User::factory()->create(['name' => 'Coach A', 'team' => 'TR']);
        $other = User::factory()->create(['name' => 'Other Coach', 'team' => 'TR']);
        $this->actingAs($other);

        $teacherId = $this->createTeacher('SK001', '홍길동');
        $report = $this->createOnsiteReport($teacherId, $author->id, 'Coach A');

        $this->expectException(AuthorizationException::class);

        app(UpdateTeacherSupportReport::class)->execute(
            'teacher_onsite_support_reports',
            (int) $report->id,
            $this->onsitePayload('거절', false),
            $other,
        );
    }

    public function test_gate_allows_admin_to_update_any_report(): void
    {
        $author = User::factory()->create(['name' => 'Coach A', 'team' => 'TR']);
        $admin = User::factory()->admin()->create();

        $teacherId = $this->createTeacher('SK001', '홍길동');
        $report = $this->createOnsiteReport($teacherId, $author->id, 'Coach A');

        $this->assertTrue(Gate::forUser($admin)->allows(
            'updateTeacherSupportReport',
            ['teacher_onsite_support_reports', (int) $report->id],
        ));
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

    private function createOnsiteReport(int $teacherId, int $createdBy, string $coachName): TeacherOnsiteSupportReport
    {
        $supportRecord = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'TR_Name' => $coachName,
            'Support_Date' => '2026-05-19',
            'Meet_Time' => '09:00:00',
            'Target' => '홍길동',
            'Support_Type' => 'On-Site',
            'Issue' => '초기',
            'Status' => '완료',
            'CreatedDate' => now(),
            'CompletedDate' => now(),
        ]);

        return TeacherOnsiteSupportReport::query()->create([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => $coachName,
            'institution_name' => '기관A',
            'teacher_name' => '홍길동',
            'support_date' => '2026-05-19',
            'other_notes' => '초기',
            'procedures' => [],
            'strength_areas' => [],
            'growth_areas' => [],
            'status' => '완료',
            'support_record_id' => $supportRecord->ID,
            'created_by' => $createdBy,
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
