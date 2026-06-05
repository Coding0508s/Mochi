<?php

namespace Tests\Unit;

use App\Models\Department;
use PHPUnit\Framework\TestCase;

class DepartmentPeopleSidebarSortTest extends TestCase
{
    public function test_ceo_and_board_of_directors_sort_before_other_departments(): void
    {
        $departments = collect([
            new Department(['DEPTNO' => 'A05', 'DEPTNAME' => 'Coach']),
            new Department(['DEPTNO' => 'A01', 'DEPTNAME' => 'CEO']),
            new Department(['DEPTNO' => 'A02', 'DEPTNAME' => 'Board of Directors']),
            new Department(['DEPTNO' => 'A03', 'DEPTNAME' => 'CS Team']),
        ]);

        $sorted = Department::sortForPeopleSidebar($departments);

        $this->assertSame(
            ['CEO', 'Board of Directors', 'CS Team', 'Coach'],
            $sorted->pluck('DEPTNAME')->all()
        );
    }
}
