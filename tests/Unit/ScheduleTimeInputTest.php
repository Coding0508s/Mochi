<?php

namespace Tests\Unit;

use App\Support\ScheduleTimeInput;
use PHPUnit\Framework\TestCase;

class ScheduleTimeInputTest extends TestCase
{
    public function test_it_accepts_standard_and_midnight_end_times(): void
    {
        $this->assertTrue(ScheduleTimeInput::isValid('23:30'));
        $this->assertTrue(ScheduleTimeInput::isValid('24:00'));
        $this->assertFalse(ScheduleTimeInput::isValid('24:30'));
        $this->assertFalse(ScheduleTimeInput::isValid('25:00'));
    }

    public function test_it_parses_midnight_end_as_end_of_day(): void
    {
        $parsed = ScheduleTimeInput::parseOnDate('2026-06-11', '24:00');

        $this->assertSame('2026-06-11 23:59:59', $parsed->format('Y-m-d H:i:s'));
    }
}
