<?php

namespace Tests\Unit;

use App\Support\TeacherSupportListActivity;
use Tests\TestCase;

class TeacherSupportListActivityTest extends TestCase
{
    public function test_latest_support_date_expression_uses_union_max_on_sqlite(): void
    {
        $expression = TeacherSupportListActivity::latestSupportDateSqlExpression(2026);

        $this->assertStringContainsString('UNION ALL', $expression);
        $this->assertStringContainsString('MAX(activity_date)', $expression);
        $this->assertStringContainsString('Teachers._1st_Support_Date', $expression);
    }
}
