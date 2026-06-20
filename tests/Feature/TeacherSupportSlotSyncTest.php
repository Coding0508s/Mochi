<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Support\TeacherSupportSlotSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeacherSupportSlotSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    protected function createRequiredTables(): void
    {
        Schema::dropIfExists('Teachers');

        Schema::create('Teachers', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 255)->nullable();
            $table->string('_1st_Support_Date')->nullable();
            $table->string('_2nd_Support_Date')->nullable();
            $table->string('_3rd_Support_Date')->nullable();
            $table->string('_4th_Support_Date')->nullable();
            $table->string('_1st_Support_Type')->nullable();
            $table->string('_2nd_Support_Type')->nullable();
            $table->string('_3rd_Support_Type')->nullable();
            $table->string('_4th_Support_Type')->nullable();
        });
    }

    private function createTeacher(array $attributes = []): Teacher
    {
        return Teacher::query()->create(array_merge([
            'SK_Code' => 'SK001',
            'Name' => '홍길동',
        ], $attributes));
    }

    public function test_apply_records_date_and_type_for_selected_round(): void
    {
        Carbon::setTestNow('2026-06-10 11:00:00');

        $teacher = $this->createTeacher();

        TeacherSupportSlotSync::apply($teacher, 1, 'On-Site');

        $teacher->refresh();
        // LegacyDateTimeCast(ExcelSerialDate)는 날짜 단위(Y-m-d)로 저장한다.
        $this->assertSame('2026-06-10', $teacher->_1st_Support_Date?->format('Y-m-d'));
        $this->assertSame('On-Site', $teacher->_1st_Support_Type);

        Carbon::setTestNow();
    }

    public function test_apply_with_null_round_does_nothing(): void
    {
        $teacher = $this->createTeacher();

        TeacherSupportSlotSync::apply($teacher, null, 'On-Site');

        $teacher->refresh();
        $this->assertNull($teacher->_1st_Support_Date);
        $this->assertNull($teacher->_1st_Support_Type);
    }

    public function test_apply_rejects_already_recorded_round(): void
    {
        $teacher = $this->createTeacher([
            '_2nd_Support_Date' => '2026-01-15 10:00:00',
        ]);

        try {
            TeacherSupportSlotSync::apply($teacher, 2, 'Open-Class');
            $this->fail('ValidationException expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(TeacherSupportSlotSync::ERROR_KEY, $e->errors());
        }

        $teacher->refresh();
        $this->assertNull($teacher->_2nd_Support_Type);
    }

    public function test_apply_rejects_invalid_round(): void
    {
        $teacher = $this->createTeacher();

        $this->expectException(ValidationException::class);

        TeacherSupportSlotSync::apply($teacher, 5, 'On-Site');
    }

    public function test_first_empty_round_skips_recorded_rounds(): void
    {
        $teacher = $this->createTeacher([
            '_1st_Support_Date' => '2026-01-10 09:00:00',
            '_2nd_Support_Date' => '2026-02-10 09:00:00',
        ]);

        $this->assertSame(3, TeacherSupportSlotSync::firstEmptyRound($teacher));
    }

    public function test_first_empty_round_returns_null_when_all_recorded(): void
    {
        $teacher = $this->createTeacher([
            '_1st_Support_Date' => '2026-01-10 09:00:00',
            '_2nd_Support_Date' => '2026-02-10 09:00:00',
            '_3rd_Support_Date' => '2026-03-10 09:00:00',
            '_4th_Support_Date' => '2026-04-10 09:00:00',
        ]);

        $this->assertNull(TeacherSupportSlotSync::firstEmptyRound($teacher));
    }

    public function test_recorded_rounds_lists_only_filled_rounds(): void
    {
        $teacher = $this->createTeacher([
            '_1st_Support_Date' => '2026-01-10 09:00:00',
            '_3rd_Support_Date' => '2026-03-10 09:00:00',
        ]);

        $this->assertSame([1, 3], TeacherSupportSlotSync::recordedRounds($teacher));
    }

    public function test_empty_string_legacy_value_counts_as_empty(): void
    {
        $teacher = $this->createTeacher();
        Teacher::query()->whereKey($teacher->ID)->update(['_1st_Support_Date' => '']);
        $teacher->refresh();

        $this->assertFalse(TeacherSupportSlotSync::isRoundRecorded($teacher, 1));
        $this->assertSame(1, TeacherSupportSlotSync::firstEmptyRound($teacher));
    }

    public function test_clear_matching_completion_removes_synced_round(): void
    {
        $teacher = $this->createTeacher([
            '_1st_Support_Date' => '2026-06-18',
            '_1st_Support_Type' => '교사 지원 및 참관',
        ]);

        TeacherSupportSlotSync::clearMatchingCompletion($teacher, '교사 지원 및 참관', '2026-06-18');

        $teacher->refresh();
        $this->assertNull($teacher->_1st_Support_Date);
        $this->assertNull($teacher->_1st_Support_Type);
    }
}
