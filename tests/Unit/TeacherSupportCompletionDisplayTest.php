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
        Schema::dropIfExists('S_Support_NewTeacher');
        Schema::dropIfExists('S_Support_OnSite');
        Schema::dropIfExists('S_SolutionConsulting');
        Schema::dropIfExists('S_Support_LVA');
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

        Schema::create('S_Support_NewTeacher', function ($table): void {
            $table->increments('ID');
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_Support_OnSite', function ($table): void {
            $table->increments('ID');
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_SolutionConsulting', function ($table): void {
            $table->increments('ID');
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_Support_LVA', function ($table): void {
            $table->increments('ID');
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
            $table->unsignedTinyInteger('ReportType')->nullable();
            $table->string('LVA_TYPE', 10)->nullable();
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

    public function test_parts_falls_back_to_legacy_new_teacher_when_teacher_slot_empty(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '신규교사',
        ]);

        DB::table('S_Support_NewTeacher')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-02-17 00:00:00',
            'Status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $parts = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);

        $this->assertSame('2024-02-17', $parts['date']);
        $this->assertSame('교사 지원(신규교사)', $parts['type']);
    }

    public function test_parts_falls_back_to_mochi_report_when_year_filter_is_all(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '전체연도고아',
        ]);

        DB::table('teacher_visit_support_reports')->insert([
            'teacher_id' => $teacherId,
            'support_date' => '2026-03-15',
            'status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $parts = TeacherSupportCompletionDisplay::parts($teacher, 1, null);

        $this->assertSame('2026-03-15', $parts['date']);
        $this->assertSame('교사 지원 및 참관', $parts['type']);
    }

    public function test_legacy_new_teacher_does_not_duplicate_into_second_round(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '레거시중복방지',
            '_1st_Support_Date' => '2024-02-17',
            '_1st_Support_Type' => '교사 지원(신규교사)',
        ]);

        DB::table('S_Support_NewTeacher')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-02-17 00:00:00',
            'Status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $first = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);
        $second = TeacherSupportCompletionDisplay::parts($teacher, 2, 2024);

        $this->assertSame('2024-02-17', $first['date']);
        $this->assertSame('교사 지원(신규교사)', $first['type']);
        $this->assertSame('', $second['date']);
    }

    public function test_parts_falls_back_to_legacy_onsite_when_teacher_slot_empty(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '온사이트교사',
        ]);

        DB::table('S_Support_OnSite')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-06-27 00:00:00',
            'Status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $parts = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);

        $this->assertSame('2024-06-27', $parts['date']);
        $this->assertSame('교사 지원 On-Site', $parts['type']);
    }

    public function test_parts_falls_back_to_legacy_pro_con_when_teacher_slot_empty(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '프로콘교사',
        ]);

        DB::table('S_SolutionConsulting')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-11-22 00:00:00',
            'Status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $parts = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);

        $this->assertSame('2024-11-22', $parts['date']);
        $this->assertSame('Pro Con', $parts['type']);
    }

    public function test_parts_falls_back_to_legacy_lva_with_resolved_type(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => 'LVA교사',
        ]);

        DB::table('S_Support_LVA')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-03-10 00:00:00',
            'Status' => '완료',
            'ReportType' => 3,
            'LVA_TYPE' => 'FB',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $parts = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);

        $this->assertSame('2024-03-10', $parts['date']);
        $this->assertSame('교사 지원 LVA FB', $parts['type']);
    }

    public function test_legacy_onsite_does_not_duplicate_into_second_round_when_slot_matches(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '온사이트중복방지',
            '_1st_Support_Date' => '2024-06-27',
            '_1st_Support_Type' => '교사 지원 On-Site',
        ]);

        DB::table('S_Support_OnSite')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-06-27 00:00:00',
            'Status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $first = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);
        $second = TeacherSupportCompletionDisplay::parts($teacher, 2, 2024);

        $this->assertSame('2024-06-27', $first['date']);
        $this->assertSame('교사 지원 On-Site', $first['type']);
        $this->assertSame('', $second['date']);
    }

    public function test_multiple_legacy_types_fill_sequential_empty_rounds(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '다건교사',
        ]);

        DB::table('S_Support_OnSite')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-06-27 00:00:00',
            'Status' => '완료',
        ]);

        DB::table('S_SolutionConsulting')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-11-22 00:00:00',
            'Status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $first = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);
        $second = TeacherSupportCompletionDisplay::parts($teacher, 2, 2024);

        $this->assertSame('2024-06-27', $first['date']);
        $this->assertSame('교사 지원 On-Site', $first['type']);
        $this->assertSame(0, $first['extra']);
        $this->assertSame('2024-11-22', $second['date']);
        $this->assertSame('Pro Con', $second['type']);
        $this->assertSame(0, $second['extra']);
    }

    public function test_duplicate_same_date_type_shows_extra_count_on_round(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '중복프로콘교사',
        ]);

        DB::table('S_SolutionConsulting')->insert([
            [
                'TeacherId' => $teacherId,
                'SupportDate' => '2024-11-22 00:00:00',
                'Status' => '완료',
            ],
            [
                'TeacherId' => $teacherId,
                'SupportDate' => '2024-11-22 00:00:00',
                'Status' => '완료',
            ],
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $parts = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);
        $display = TeacherSupportCompletionDisplay::displayWithType($teacher, 1, 2024);

        $this->assertSame('2024-11-22', $parts['date']);
        $this->assertSame('Pro Con', $parts['type']);
        $this->assertSame(1, $parts['extra']);
        $this->assertSame('2024-11-22 (Pro Con) 외 1건', $display);

        $second = TeacherSupportCompletionDisplay::parts($teacher, 2, 2024);
        $this->assertSame('', $second['date']);
        $this->assertSame(0, $second['extra']);
    }

    public function test_teacher_slot_with_matching_duplicate_reports_shows_extra(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '슬롯중복교사',
            '_1st_Support_Date' => '2024-11-22',
            '_1st_Support_Type' => 'Pro Con',
        ]);

        DB::table('S_SolutionConsulting')->insert([
            [
                'TeacherId' => $teacherId,
                'SupportDate' => '2024-11-22 00:00:00',
                'Status' => '완료',
            ],
            [
                'TeacherId' => $teacherId,
                'SupportDate' => '2024-11-22 00:00:00',
                'Status' => '완료',
            ],
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $first = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);
        $second = TeacherSupportCompletionDisplay::parts($teacher, 2, 2024);

        $this->assertSame('2024-11-22', $first['date']);
        $this->assertSame('Pro Con', $first['type']);
        $this->assertSame(1, $first['extra']);
        $this->assertSame('', $second['date']);
    }

    public function test_same_date_different_types_collapse_into_one_round_with_extra(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '같은날다타입',
        ]);

        DB::table('S_Support_OnSite')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-06-27 00:00:00',
            'Status' => '완료',
        ]);

        DB::table('S_SolutionConsulting')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-06-27 00:00:00',
            'Status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $first = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);
        $second = TeacherSupportCompletionDisplay::parts($teacher, 2, 2024);
        $display = TeacherSupportCompletionDisplay::displayWithType($teacher, 1, 2024);

        $this->assertSame('2024-06-27', $first['date']);
        $this->assertSame(1, $first['extra']);
        $this->assertStringContainsString('외 1건', $display);
        $this->assertSame('', $second['date']);
    }

    public function test_teacher_slot_counts_same_date_other_types_as_extra(): void
    {
        $teacherId = DB::table('Teachers')->insertGetId([
            'Name' => '슬롯다른타입',
            '_1st_Support_Date' => '2024-06-27',
            '_1st_Support_Type' => '교사 지원 On-Site',
        ]);

        DB::table('S_Support_OnSite')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-06-27 00:00:00',
            'Status' => '완료',
        ]);

        DB::table('S_SolutionConsulting')->insert([
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-06-27 00:00:00',
            'Status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);

        $first = TeacherSupportCompletionDisplay::parts($teacher, 1, 2024);
        $second = TeacherSupportCompletionDisplay::parts($teacher, 2, 2024);

        $this->assertSame('2024-06-27', $first['date']);
        $this->assertSame('교사 지원 On-Site', $first['type']);
        $this->assertSame(1, $first['extra']);
        $this->assertSame('', $second['date']);
    }
}
