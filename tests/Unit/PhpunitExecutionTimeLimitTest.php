<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PhpunitExecutionTimeLimitTest extends TestCase
{
    public function test_max_execution_time_is_disabled_so_the_full_suite_can_finish(): void
    {
        $this->assertSame(0, (int) ini_get('max_execution_time'));
    }
}
