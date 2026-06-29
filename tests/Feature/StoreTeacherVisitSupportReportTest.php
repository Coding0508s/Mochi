<?php

namespace Tests\Feature;

use App\Actions\StoreTeacherVisitSupportReport;
use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\TeacherVisitSupportReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreTeacherVisitSupportReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    protected function createRequiredTables(): void
    {
        Schema::dropIfExists('teacher_visit_support_reports');
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
            $table->string('School_Name', 255)->nullable();
            $table->string('Name', 255)->nullable();
            $table->boolean('ClassInOut')->default(true);
            $table->string('_1st_Support_Date')->nullable();
            $table->string('_2nd_Support_Date')->nullable();
            $table->string('_3rd_Support_Date')->nullable();
            $table->string('_4th_Support_Date')->nullable();
            $table->string('_1st_Support_Type')->nullable();
            $table->string('_2nd_Support_Type')->nullable();
            $table->string('_3rd_Support_Type')->nullable();
            $table->string('_4th_Support_Type')->nullable();
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
            $table->string('Issue', 255)->nullable();
            $table->text('TO_Account')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CreatedDate')->nullable();
            $table->timestamp('CompletedDate')->nullable();
        });

        Schema::create('teacher_visit_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('support_location', 255)->nullable();
            $table->string('support_purpose', 100);
            $table->unsignedTinyInteger('observe_unit')->nullable();
            $table->unsignedTinyInteger('observe_lesson')->nullable();
            $table->string('observe_summary_extra', 255)->nullable();
            $table->string('observe_class', 50)->nullable();
            $table->string('observe_age', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('meeting_type', 50)->nullable();
            $table->text('pre_request_notes')->nullable();
            $table->text('monitoring_feedback')->nullable();
            $table->text('interview_and_action_plan')->nullable();
            $table->text('special_notes')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_completed_report_creates_support_record_and_syncs_round(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->visitPayload(true, 1),
            $admin,
        );

        $teacher->refresh();
        $this->assertSame('2026-06-18', $teacher->_1st_Support_Date?->format('Y-m-d'));
        $this->assertSame('교사 지원 및 참관', $teacher->_1st_Support_Type);

        $this->assertDatabaseHas('teacher_visit_support_reports', [
            'teacher_id' => $teacher->ID,
            'support_location' => '분당 ○○어학원',
            'status' => '완료',
        ]);
        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Support_Type' => '교사 지원 및 참관',
            'Status' => '완료',
        ]);
    }

    public function test_draft_report_creates_in_progress_support_record_without_slot_sync(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->visitPayload(false, 1),
            $admin,
        );

        $teacher->refresh();
        $this->assertNull($teacher->_1st_Support_Date);
        $this->assertDatabaseHas('teacher_visit_support_reports', [
            'teacher_id' => $teacher->ID,
            'status' => '임시',
        ]);
        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Support_Type' => '교사 지원 및 참관',
            'Status' => '진행중',
        ]);

        $visitReport = TeacherVisitSupportReport::query()->first();
        $this->assertNotNull($visitReport?->support_record_id);
    }

    public function test_completed_report_stores_long_visit_notes_in_to_account_not_issue(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();
        $longFeedback = str_repeat('모니터링 피드백 내용입니다. ', 80);
        $longActionPlan = str_repeat('후속 조치 계획입니다. ', 80);

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            array_merge($this->visitPayload(true, 1), [
                'monitoring_feedback' => $longFeedback,
                'interview_and_action_plan' => $longActionPlan,
            ]),
            $admin,
        );

        $supportRecord = SupportRecord::query()->first();
        $this->assertNotNull($supportRecord);
        $this->assertNull($supportRecord->Issue);
        $this->assertGreaterThan(2000, mb_strlen((string) $supportRecord->TO_Account));
        $this->assertStringContainsString('모니터링 피드백 내용입니다.', (string) $supportRecord->TO_Account);
        $this->assertStringContainsString('후속 조치 계획입니다.', (string) $supportRecord->TO_Account);
    }

    public function test_completed_report_includes_section_headers_in_to_account(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->visitPayload(true, 1),
            $admin,
        );

        $toAccount = (string) SupportRecord::query()->value('TO_Account');
        $this->assertStringContainsString("사전 요청 및 주요 이슈\n발화 참여율 개선 요청", $toAccount);
        $this->assertStringContainsString("세부 지원 내용\n학생 반응은 좋았으나", $toAccount);
        $this->assertStringContainsString("면담 내용 및 Action Plan\n차주까지", $toAccount);
        $this->assertStringContainsString("특이사항\n없음", $toAccount);
    }

    public function test_completed_report_does_not_send_support_report_stored_mail(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['visit-notify@test.org'],
        ]);

        $admin = User::factory()->admin()->create(['team' => 'COACH']);
        $teacher = $this->createTeacher();

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->visitPayload(true, 1),
            $admin,
        );

        Mail::assertNothingSent();
    }

    public function test_draft_report_does_not_send_support_report_stored_mail(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['visit-notify@test.org'],
        ]);

        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->visitPayload(false, 1),
            $admin,
        );

        Mail::assertNothingSent();
    }

    public function test_support_purpose_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();
        $payload = $this->visitPayload(true, 1);
        unset($payload['support_purpose']);

        $this->expectException(ValidationException::class);

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            $payload,
            $admin,
        );
    }

    public function test_completed_report_requires_monitoring_feedback(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();
        $payload = $this->visitPayload(true, 1);
        unset($payload['monitoring_feedback'], $payload['interview_and_action_plan']);

        try {
            app(StoreTeacherVisitSupportReport::class)->execute(
                (int) $teacher->ID,
                $payload,
                $admin,
            );
            $this->fail('ValidationException was expected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('monitoring_feedback', $exception->errors());
        }
    }

    public function test_in_progress_report_allows_empty_monitoring_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();
        $payload = $this->visitPayload(false, null);
        unset($payload['monitoring_feedback'], $payload['interview_and_action_plan']);

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            $payload,
            $admin,
        );

        $this->assertDatabaseHas('teacher_visit_support_reports', [
            'teacher_id' => $teacher->ID,
            'status' => '임시',
            'monitoring_feedback' => null,
            'interview_and_action_plan' => null,
        ]);
    }

    public function test_rejects_duplicate_completed_report_on_same_teacher_and_date(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->visitPayload(true, 1),
            $admin,
        );

        $this->expectException(ValidationException::class);

        app(StoreTeacherVisitSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->visitPayload(true, 2),
            $admin,
        );
    }

    private function createTeacher(array $attributes = []): Teacher
    {
        return Teacher::query()->create(array_merge([
            'SK_Code' => 'SK001',
            'School_Name' => '모찌 어학원',
            'Name' => '홍길동',
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function visitPayload(bool $markCompleted, ?int $supportRound): array
    {
        $payload = [
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '모찌 어학원',
            'teacher_name' => '홍길동',
            'support_date' => '2026-06-18',
            'support_location' => '분당 ○○어학원',
            'support_purpose' => '정기 수업 참관',
            'meeting_type' => 'On-Site',
            'pre_request_notes' => '발화 참여율 개선 요청',
            'monitoring_feedback' => '학생 반응은 좋았으나 교사 발화 속도 조절 필요',
            'interview_and_action_plan' => '차주까지 피드백 반영 후 재점검',
            'special_notes' => '없음',
            'mark_completed' => $markCompleted,
        ];

        if ($supportRound !== null) {
            $payload['support_round'] = $supportRound;
        }

        return $payload;
    }
}
