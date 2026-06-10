<?php

namespace Tests\Feature;

use App\Actions\StoreTeacherOnsiteSupportReport;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Store 액션의 완료 처리 → Teachers N차 완료 현황 동기화 통합 테스트.
 * 10개 액션이 동일한 패턴이므로 Onsite 액션을 대표로 검증한다.
 */
class StoreTeacherSupportReportRoundSyncTest extends TestCase
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
            $table->integer('observe_unit')->nullable();
            $table->integer('observe_lesson')->nullable();
            $table->string('observe_summary_extra', 255)->nullable();
            $table->string('observe_class', 50)->nullable();
            $table->string('observe_age', 50)->nullable();
            $table->string('teacher_experience', 50)->nullable();
            $table->integer('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
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
    private function onsitePayload(bool $markCompleted, ?int $supportRound): array
    {
        // sk_code 등은 검증 통과용 입력값이며 저장 시 trusted context 로 대체된다.
        $payload = [
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '모찌 어학원',
            'teacher_name' => '홍길동',
            'support_date' => '2026-06-10',
            'mark_completed' => $markCompleted,
        ];

        if ($supportRound !== null) {
            $payload['support_round'] = $supportRound;
        }

        return $payload;
    }

    public function test_completed_report_with_round_fills_teacher_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();

        app(StoreTeacherOnsiteSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->onsitePayload(true, 1),
            $admin,
        );

        $teacher->refresh();
        $this->assertSame(now()->format('Y-m-d'), $teacher->_1st_Support_Date?->format('Y-m-d'));
        $this->assertSame('On-Site', $teacher->_1st_Support_Type);

        $this->assertDatabaseHas('teacher_onsite_support_reports', [
            'teacher_id' => $teacher->ID,
            'status' => '완료',
        ]);
    }

    public function test_completed_report_without_round_skips_teacher_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();

        app(StoreTeacherOnsiteSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->onsitePayload(true, null),
            $admin,
        );

        $teacher->refresh();
        $this->assertNull($teacher->_1st_Support_Date);
        $this->assertNull($teacher->_1st_Support_Type);
        $this->assertDatabaseHas('teacher_onsite_support_reports', ['status' => '완료']);
    }

    public function test_occupied_round_rolls_back_whole_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher([
            '_1st_Support_Date' => '2026-01-10',
            '_1st_Support_Type' => '방문',
        ]);

        try {
            app(StoreTeacherOnsiteSupportReport::class)->execute(
                (int) $teacher->ID,
                $this->onsitePayload(true, 1),
                $admin,
            );
            $this->fail('ValidationException expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('support_round', $e->errors());
        }

        $this->assertDatabaseCount('teacher_onsite_support_reports', 0);
        $this->assertDatabaseCount('S_SupportInfo_Account', 0);

        $teacher->refresh();
        $this->assertSame('방문', $teacher->_1st_Support_Type);
    }

    public function test_draft_report_ignores_round(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();

        app(StoreTeacherOnsiteSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->onsitePayload(false, 1),
            $admin,
        );

        $teacher->refresh();
        $this->assertNull($teacher->_1st_Support_Date);
        $this->assertDatabaseHas('teacher_onsite_support_reports', ['status' => '임시']);
    }

    public function test_round_out_of_range_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = $this->createTeacher();

        $this->expectException(ValidationException::class);

        app(StoreTeacherOnsiteSupportReport::class)->execute(
            (int) $teacher->ID,
            $this->onsitePayload(true, 5),
            $admin,
        );
    }
}
