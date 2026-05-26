<?php

namespace Tests\Unit;

use App\Models\SupportRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
