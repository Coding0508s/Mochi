<?php

namespace Tests\Unit;

use App\Support\ImportExecutionTime;
use Tests\TestCase;

class ImportExecutionTimeTest extends TestCase
{
    public function test_extend_for_long_import_does_not_cap_phpunit_unlimited_time(): void
    {
        $this->assertSame(0, (int) ini_get('max_execution_time'));

        ImportExecutionTime::extendForLongImport(120);

        $this->assertSame(0, (int) ini_get('max_execution_time'));
    }
}
