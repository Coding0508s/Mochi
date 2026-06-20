<?php

namespace Tests\Feature;

use App\Support\TeacherSupportHistoryAggregator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * forInstitution() N+1 회귀 방지 테스트.
 *
 * 교사 N명·SK코드 2개여도 레거시/MOCHI 보고서 테이블은 각각 1회만 조회해야 한다.
 * (이전: SK코드별 legacy 조회, 교사별 mochi 조회로 쿼리가 곱셈으로 증가)
 */
class TeacherSupportHistoryAggregatorBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacyTables();
    }

    private function createLegacyTables(): void
    {
        Schema::dropIfExists('Teachers');
        Schema::create('Teachers', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 255)->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::dropIfExists('S_Support_NewTeacher');
        Schema::create('S_Support_NewTeacher', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
            $table->unsignedTinyInteger('ReportType')->nullable();
        });
    }

    public function test_for_institution_batches_report_queries_without_n_plus_one(): void
    {
        // 2개 SK코드 아래 3명의 재직 교사
        $teacherA1 = (int) DB::table('Teachers')->insertGetId(['SK_Code' => 'SK-A', 'Name' => '교사 A1', 'Status' => '재직']);
        $teacherA2 = (int) DB::table('Teachers')->insertGetId(['SK_Code' => 'SK-A', 'Name' => '교사 A2', 'Status' => '재직']);
        $teacherB1 = (int) DB::table('Teachers')->insertGetId(['SK_Code' => 'SK-B', 'Name' => '교사 B1', 'Status' => '재직']);

        // 레거시(SK_Code 기준) 보고서 — SK코드마다 1건
        DB::table('S_Support_NewTeacher')->insert([
            ['TR_Name' => '코치1', 'SK_Code' => 'SK-A', 'Teacher' => '교사 A1', 'TeacherId' => $teacherA1, 'SupportDate' => '2026-01-10 00:00:00', 'Status' => '완료', 'ReportType' => 1],
            ['TR_Name' => '코치2', 'SK_Code' => 'SK-B', 'Teacher' => '교사 B1', 'TeacherId' => $teacherB1, 'SupportDate' => '2026-02-10 00:00:00', 'Status' => '완료', 'ReportType' => 1],
        ]);

        // MOCHI(teacher_id 기준) 보고서 — 교사마다 1건
        foreach ([$teacherA1, $teacherA2, $teacherB1] as $i => $teacherId) {
            DB::table('teacher_demo_lesson_support_reports')->insert([
                'teacher_id' => $teacherId,
                'sk_code' => $teacherId === $teacherB1 ? 'SK-B' : 'SK-A',
                'coach_name' => '코치'.$i,
                'institution_name' => '기관',
                'teacher_name' => '교사 '.$teacherId,
                'support_date' => '2026-03-1'.$i,
                'status' => '완료',
            ]);
        }

        // 데이터 SELECT(`from "테이블"`)만 집계한다. 스키마 조회(sqlite_master/pragma)는 제외.
        $dataQueryCount = [];
        DB::listen(function ($query) use (&$dataQueryCount): void {
            foreach (['S_Support_NewTeacher', 'teacher_demo_lesson_support_reports'] as $table) {
                if (str_contains($query->sql, 'from "'.$table.'"')) {
                    $dataQueryCount[$table] = ($dataQueryCount[$table] ?? 0) + 1;
                }
            }
        });

        $records = app(TeacherSupportHistoryAggregator::class)
            ->forInstitution(['SK-A', 'SK-B'], limit: 100);

        // 결과 동일: 레거시 2건 + MOCHI 3건 = 5건
        $this->assertCount(5, $records);

        $types = collect($records)->pluck('type');
        $this->assertSame(3, $types->filter(fn ($t) => $t === '신규교사 시연수업')->count());
        $this->assertSame(2, $types->filter(fn ($t) => $t === '교사 지원(신규교사)')->count());

        // 핵심: 교사 3명·SK 2개여도 각 보고서 테이블은 데이터 조회 1회만 (배치화)
        $this->assertSame(1, $dataQueryCount['teacher_demo_lesson_support_reports'] ?? 0,
            'MOCHI 보고서 테이블이 교사 수만큼 반복 조회되면 안 된다');
        $this->assertSame(1, $dataQueryCount['S_Support_NewTeacher'] ?? 0,
            '레거시 보고서 테이블이 SK코드 수만큼 반복 조회되면 안 된다');
    }
}
