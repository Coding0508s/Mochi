<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Support\ExcelSerialDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NormalizeTeacherPlanDatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('Teachers');
        Schema::create('Teachers', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 255)->nullable();
            $table->boolean('ClassInOut')->default(true);
            $table->string('Plan_1st_Support_Date')->nullable();
            $table->string('Plan_2nd_Support_Date')->nullable();
        });
    }

    public function test_teacher_cast_reads_excel_serial_as_real_date(): void
    {
        $id = DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK9999',
            'Name' => '테스트 교사',
            'ClassInOut' => true,
            'Plan_1st_Support_Date' => '45809',
        ]);

        $teacher = Teacher::query()->findOrFail($id);

        $this->assertSame('2025-06-01', $teacher->Plan_1st_Support_Date?->toDateString());
        $this->assertSame('2025년 6월', ExcelSerialDate::formatPlanMonth($teacher->Plan_1st_Support_Date));
    }

    public function test_normalize_command_converts_serial_values(): void
    {
        DB::table('Teachers')->insert([
            'SK_Code' => 'SK1000',
            'Name' => 'A',
            'ClassInOut' => true,
            'Plan_1st_Support_Date' => '45809',
            'Plan_2nd_Support_Date' => '45778',
        ]);

        $this->artisan('teachers:normalize-plan-dates')
            ->assertSuccessful();

        $row = DB::table('Teachers')->where('SK_Code', 'SK1000')->first();

        $this->assertSame('2025-06-01', $row->Plan_1st_Support_Date);
        $this->assertSame('2025-05-01', $row->Plan_2nd_Support_Date);
    }

    public function test_normalize_command_dry_run_does_not_update(): void
    {
        DB::table('Teachers')->insert([
            'SK_Code' => 'SK2000',
            'Name' => 'B',
            'ClassInOut' => true,
            'Plan_1st_Support_Date' => '45809',
        ]);

        Artisan::call('teachers:normalize-plan-dates', ['--dry-run' => true]);

        $row = DB::table('Teachers')->where('SK_Code', 'SK2000')->first();

        $this->assertSame('45809', $row->Plan_1st_Support_Date);
    }
}
