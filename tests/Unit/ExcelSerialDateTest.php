<?php

namespace Tests\Unit;

use App\Support\ExcelSerialDate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExcelSerialDateTest extends TestCase
{
    public function test_detects_excel_serial_numbers(): void
    {
        $this->assertTrue(ExcelSerialDate::isSerial('45809'));
        $this->assertTrue(ExcelSerialDate::isSerial(45778));
        $this->assertFalse(ExcelSerialDate::isSerial('2025-06-01'));
        $this->assertFalse(ExcelSerialDate::isSerial(null));
        $this->assertFalse(ExcelSerialDate::isSerial('1970-01-01'));
    }

    public function test_converts_excel_serial_to_expected_date(): void
    {
        $this->assertSame(
            '2025-06-01',
            ExcelSerialDate::fromSerial(45809)->toDateString(),
        );

        $this->assertSame(
            '2025-05-01',
            ExcelSerialDate::fromSerial(45778)->toDateString(),
        );
    }

    public function test_parse_returns_null_for_empty_or_epoch_artifact(): void
    {
        $this->assertNull(ExcelSerialDate::parse(null));
        $this->assertNull(ExcelSerialDate::parse(''));
        $this->assertNull(ExcelSerialDate::parse('0000-00-00'));
        $this->assertNull(ExcelSerialDate::parse('1970-01-01'));
    }

    public function test_parse_converts_serial_string(): void
    {
        $parsed = ExcelSerialDate::parse('45809');

        $this->assertNotNull($parsed);
        $this->assertSame('2025-06-01', $parsed->toDateString());
    }

    public function test_format_plan_month_returns_korean_label_or_empty(): void
    {
        $this->assertSame('2025년 6월', ExcelSerialDate::formatPlanMonth(ExcelSerialDate::parse('45809')));
        $this->assertSame('', ExcelSerialDate::formatPlanMonth(null));
        $this->assertSame('', ExcelSerialDate::formatPlanMonth(ExcelSerialDate::parse('1970-01-01')));
    }

    #[DataProvider('storageStringProvider')]
    public function test_to_storage_string(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, ExcelSerialDate::toStorageString($input));
    }

    /**
     * @return array<string, array{0: mixed, 1: ?string}>
     */
    public static function storageStringProvider(): array
    {
        return [
            'serial' => ['45809', '2025-06-01'],
            'iso date' => ['2025-03-15', '2025-03-15'],
            'empty' => [null, null],
        ];
    }

    public function test_date_to_serial_round_trip(): void
    {
        $parsed = ExcelSerialDate::parse('45809');

        $this->assertNotNull($parsed);
        $this->assertSame(45809, ExcelSerialDate::dateToSerial($parsed));
    }

    public function test_is_in_year_and_year_scoped_formatters(): void
    {
        $date = '2026-03-01';

        $this->assertTrue(ExcelSerialDate::isInYear($date, 2026));
        $this->assertFalse(ExcelSerialDate::isInYear($date, 2025));
        $this->assertSame('2026년 3월', ExcelSerialDate::formatPlanMonthForYear($date, 2026));
        $this->assertSame('', ExcelSerialDate::formatPlanMonthForYear($date, 2025));
        $this->assertSame('2026-03-01', ExcelSerialDate::toStorageStringForYear($date, 2026));
        $this->assertNull(ExcelSerialDate::toStorageStringForYear($date, 2025));
    }

    public function test_display_completed_with_type_appends_type_label(): void
    {
        $this->assertSame(
            '2025-07-02 (Open-Class)',
            ExcelSerialDate::displayCompletedWithType('2025-07-02', 'Open-Class', 2025),
        );
        $this->assertSame(
            '2025-07-02',
            ExcelSerialDate::displayCompletedWithType('2025-07-02', '', 2025),
        );
        $this->assertSame(
            '',
            ExcelSerialDate::displayCompletedWithType('2025-07-02', 'Open-Class', 2026),
        );
    }

    public function test_completed_display_parts_splits_date_and_type(): void
    {
        $this->assertSame(
            ['date' => '2025-07-02', 'type' => 'Open-Class'],
            ExcelSerialDate::completedDisplayParts('2025-07-02', 'Open-Class', 2025),
        );
        $this->assertSame(
            ['date' => '2025-07-02', 'type' => ''],
            ExcelSerialDate::completedDisplayParts('2025-07-02', '', 2025),
        );
        $this->assertSame(
            ['date' => '', 'type' => ''],
            ExcelSerialDate::completedDisplayParts('2025-07-02', 'Open-Class', 2026),
        );
    }

    public function test_sql_column_in_year_excludes_empty_string_values(): void
    {
        $expression = ExcelSerialDate::sqlColumnInYear('Teachers.Plan_1st_Support_Date', 2026);

        $this->assertStringContainsString("NULLIF(TRIM(CAST(Teachers.Plan_1st_Support_Date AS TEXT)), '') IS NOT NULL", $expression);
    }

    public function test_sql_normalized_date_column_uses_nullif_for_empty_string(): void
    {
        $expression = ExcelSerialDate::sqlNormalizedDateColumn('Teachers.Plan_1st_Support_Date');

        $this->assertStringContainsString("NULLIF(Teachers.Plan_1st_Support_Date, '')", $expression);
    }
}
