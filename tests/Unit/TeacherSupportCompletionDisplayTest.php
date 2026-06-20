<?php

namespace Tests\Unit;

use App\Models\Teacher;
use App\Support\TeacherSupportCompletionDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeacherSupportCompletionDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TeacherSupportCompletionDisplay::flushRequestCache();

        Schema::dropIfExists('teacher_visit_support_reports');
        Schema::dropIfExists('Teachers');

        Schema::create('Teachers', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 255)->nullable();
            $table->date('_1st_Support_Date')->nullable();
            $table->string('_1st_Support_Type', 100)->nullable();
            $table->date('_2nd_Support_Date')->nullable();
            $table->string('_2nd_Support_Type', 100)->nullable();
        });

        Schema::create('teacher_visit_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->date('support_date');
            $table->string('status', 20);
        });
    }

    public function test_parts_uses_teacher_slot_when_recorded(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '김교사',
            '_1st_Support_Date' => '2026-04-01',
            '_1st_Support_Type' => 'On-Site',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $parts = TeacherSupportCompletionDisplay::parts($teacher, 1, 2026);

        $this->assertSame('2026-04-01', $parts['date']);
        $this->assertSame('On-Site', $parts['type']);
    }

    public function test_parts_falls_back_to_mochi_report_when_teacher_slot_empty(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '홍길동',
        ]);

        DB::table('teacher_visit_support_reports')->insert([
            'teacher_id' => $teacherId,
            'support_date' => '2026-03-15',
            'status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $parts = TeacherSupportCompletionDisplay::parts($teacher, 1, 2026);

        $this->assertSame('2026-03-15', $parts['date']);
        $this->assertSame('교사 지원 및 참관', $parts['type']);
    }

    public function test_year_filter_does_not_duplicate_mochi_report_into_second_round(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '중복방지교사',
            '_1st_Support_Date' => '2026-06-18',
            '_1st_Support_Type' => '교사 지원 및 참관',
        ]);

        DB::table('teacher_visit_support_reports')->insert([
            'teacher_id' => $teacherId,
            'support_date' => '2026-06-18',
            'status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $first = TeacherSupportCompletionDisplay::parts($teacher, 1, 2026);
        $second = TeacherSupportCompletionDisplay::parts($teacher, 2, 2026);

        $this->assertSame('2026-06-18', $first['date']);
        $this->assertSame('교사 지원 및 참관', $first['type']);
        $this->assertSame('', $second['date']);
        $this->assertSame('', $second['type']);
    }

    public function test_parts_accepts_null_year_for_all_years_filter(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '전체연도',
            '_1st_Support_Date' => '2026-04-01',
            '_1st_Support_Type' => 'On-Site',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $parts = TeacherSupportCompletionDisplay::parts($teacher, 1, null);

        $this->assertSame('2026-04-01', $parts['date']);
        $this->assertSame('On-Site', $parts['type']);
    }

    public function test_preload_for_teachers_avoids_repeated_mochi_lookups(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '배치교사',
        ]);

        DB::table('teacher_visit_support_reports')->insert([
            'teacher_id' => $teacherId,
            'support_date' => '2026-03-15',
            'status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        TeacherSupportCompletionDisplay::preloadForTeachers(collect([$teacher]), 2026);

        $first = TeacherSupportCompletionDisplay::parts($teacher, 1, 2026);
        $second = TeacherSupportCompletionDisplay::parts($teacher, 2, 2026);

        $this->assertSame('2026-03-15', $first['date']);
        $this->assertSame('', $second['date']);
    }
}
