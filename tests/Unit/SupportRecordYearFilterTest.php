<?php

namespace Tests\Unit;

use App\Models\SupportRecord;
use App\Support\ExcelSerialDate;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupportRecordYearFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_distinct_filter_years_uses_support_date_when_year_column_missing(): void
    {
        $this->createSupportTableWithoutYearColumn();

        SupportRecord::query()->create([
            'SK_Code' => 'SK-1',
            'Account_Name' => '기관A',
            'Support_Date' => '2024-06-01',
            'TR_Name' => 'CO',
        ]);

        SupportRecord::query()->create([
            'SK_Code' => 'SK-2',
            'Account_Name' => '기관B',
            'Support_Date' => '2026-03-15',
            'TR_Name' => 'CO',
        ]);

        $this->assertFalse(SupportRecord::tableHasYearColumn());
        $this->assertSame([2026, 2024], SupportRecord::distinctFilterYears()->all());
    }

    public function test_distinct_filter_years_returns_empty_when_no_year_source_column(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->text('TO_Account')->nullable();
        });

        SupportRecord::query()->create([
            'TR_Name' => 'CO',
            'TO_Account' => '내용',
        ]);

        $this->assertNull(SupportRecord::yearSourceColumn());
        $this->assertTrue(SupportRecord::distinctFilterYears()->isEmpty());
        $this->assertCount(1, SupportRecord::query()->orderedForList()->get());
    }

    public function test_of_year_scope_filters_by_support_date_when_year_column_missing(): void
    {
        $this->createSupportTableWithoutYearColumn();

        SupportRecord::query()->create([
            'SK_Code' => 'SK-1',
            'Account_Name' => '기관A',
            'Support_Date' => '2024-06-01',
            'TR_Name' => 'CO',
        ]);

        SupportRecord::query()->create([
            'SK_Code' => 'SK-2',
            'Account_Name' => '기관B',
            'Support_Date' => '2026-03-15',
            'TR_Name' => 'CO',
        ]);

        $this->assertCount(1, SupportRecord::query()->ofYear(2024)->get());
        $this->assertSame('SK-1', (string) SupportRecord::query()->ofYear(2024)->value('SK_Code'));
    }

    public function test_of_year_scope_uses_support_date_when_year_column_also_exists(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->text('TO_Account')->nullable();
        });

        SupportRecord::query()->create([
            'Year' => 2025,
            'SK_Code' => 'SK-2026-DATE',
            'Account_Name' => '지원일 2026',
            'Support_Date' => '2026-02-27',
            'TR_Name' => 'CO',
        ]);

        SupportRecord::query()->create([
            'Year' => 2025,
            'SK_Code' => 'SK-2025-DATE',
            'Account_Name' => '지원일 2025',
            'Support_Date' => '2025-12-01',
            'TR_Name' => 'CO',
        ]);

        $this->assertSame('Support_Date', SupportRecord::yearSourceColumn());
        $this->assertSame([2026, 2025], SupportRecord::distinctFilterYears()->all());
        $this->assertSame('SK-2026-DATE', (string) SupportRecord::query()->ofYear(2026)->value('SK_Code'));
        $this->assertSame('SK-2025-DATE', (string) SupportRecord::query()->ofYear(2025)->value('SK_Code'));
        $this->assertCount(0, SupportRecord::query()->ofYear(2024)->get());
    }

    public function test_distinct_filter_years_includes_excel_serial_support_date(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->text('TO_Account')->nullable();
        });

        $serial2023 = (string) ExcelSerialDate::dateToSerial(Carbon::create(2023, 6, 15));

        DB::table('S_SupportInfo_Account')->insert([
            'Year' => 2025,
            'SK_Code' => 'SK-SERIAL-2023',
            'Account_Name' => '엑셀 serial 2023',
            'Support_Date' => $serial2023,
            'TR_Name' => 'CO',
        ]);

        SupportRecord::query()->create([
            'Year' => 2025,
            'SK_Code' => 'SK-2025',
            'Account_Name' => '일반 날짜 2025',
            'Support_Date' => '2025-12-01',
            'TR_Name' => 'CO',
        ]);

        $this->assertContains(2023, SupportRecord::distinctFilterYears()->all());
        $this->assertSame('SK-SERIAL-2023', (string) SupportRecord::query()->ofYear(2023)->value('SK_Code'));
    }

    public function test_distinct_filter_years_falls_back_to_year_when_support_date_blank(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->text('TO_Account')->nullable();
        });

        SupportRecord::query()->create([
            'Year' => 2023,
            'SK_Code' => 'SK-YEAR-ONLY',
            'Account_Name' => 'Year만 2023',
            'Support_Date' => null,
            'TR_Name' => 'CO',
        ]);

        $this->assertContains(2023, SupportRecord::distinctFilterYears()->all());
        $this->assertSame('SK-YEAR-ONLY', (string) SupportRecord::query()->ofYear(2023)->value('SK_Code'));
    }

    private function createSupportTableWithoutYearColumn(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->string('Meet_Time', 50)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->text('TO_Account')->nullable();
            $table->string('Status', 50)->nullable();
        });
    }
}
