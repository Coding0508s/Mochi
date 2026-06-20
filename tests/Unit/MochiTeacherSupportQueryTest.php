<?php

namespace Tests\Unit;

use App\Support\MochiTeacherSupportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MochiTeacherSupportQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('teacher_visit_support_reports');
        Schema::create('teacher_visit_support_reports', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('teacher_id')->nullable();
            $table->date('support_date')->nullable();
            $table->string('status', 20)->default('임시');
        });
    }

    public function test_teacher_ids_in_year_uses_sql_filter_not_full_table_scan(): void
    {
        \DB::table('teacher_visit_support_reports')->insert([
            ['teacher_id' => 10, 'support_date' => '2026-05-01'],
            ['teacher_id' => 20, 'support_date' => '2025-05-01'],
        ]);

        $ids = MochiTeacherSupportQuery::teacherIdsInYear(2026);

        $this->assertEqualsCanonicalizing([10], $ids);
    }

    public function test_completed_reports_for_teacher_ids_batches_single_query_path(): void
    {
        \DB::table('teacher_visit_support_reports')->insert([
            ['teacher_id' => 10, 'support_date' => '2026-05-01', 'status' => '완료'],
            ['teacher_id' => 20, 'support_date' => '2026-06-01', 'status' => '완료'],
        ]);

        $reports = MochiTeacherSupportQuery::completedReportsForTeacherIds([10, 20], 2026);

        $this->assertSame('2026-05-01', $reports[10][0]['date']);
        $this->assertSame('교사 지원 및 참관', $reports[10][0]['type']);
        $this->assertSame('2026-06-01', $reports[20][0]['date']);
    }

    public function test_latest_date_subquery_excludes_empty_string_support_date(): void
    {
        $subquery = MochiTeacherSupportQuery::latestDatePerTeacherSubquerySql(null);

        $this->assertNotNull($subquery);
        $this->assertStringContainsString("teacher_visit_support_reports.support_date != ''", $subquery);
    }
}
