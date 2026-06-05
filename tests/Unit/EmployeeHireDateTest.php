<?php

namespace Tests\Unit;

use App\Support\EmployeeHireDate;
use App\Support\ExcelSerialDate;
use Tests\TestCase;

class EmployeeHireDateTest extends TestCase
{
    public function test_default_for_storage_uses_mysql_strict_safe_placeholder(): void
    {
        $this->assertSame('1970-01-01', EmployeeHireDate::defaultForStorage());
    }

    public function test_format_display_shows_dash_for_unspecified_date(): void
    {
        $this->assertSame('-', EmployeeHireDate::formatDisplay('1970-01-01'));
        $this->assertSame('-', EmployeeHireDate::formatDisplay('0000-00-00'));
        $this->assertSame('-', EmployeeHireDate::formatDisplay(null));
    }

    public function test_format_display_shows_real_hire_date(): void
    {
        $this->assertSame('2024-03-15', EmployeeHireDate::formatDisplay('2024-03-15'));
        $this->assertSame(
            '2024-03-15',
            EmployeeHireDate::formatDisplay(ExcelSerialDate::fromSerial(45366)),
        );
    }
}
