<?php

namespace Tests\Unit;

use App\Support\DepartmentDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DepartmentDisplayTest extends TestCase
{
    #[DataProvider('nameProvider')]
    public function test_name(?string $deptNo, ?string $deptName, string $expected): void
    {
        $this->assertSame($expected, DepartmentDisplay::name($deptNo, $deptName));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string}>
     */
    public static function nameProvider(): array
    {
        return [
            'a05 training legacy' => ['A05', 'Training', 'Coach'],
            'a05 coach' => ['A05', 'Coach', 'Coach'],
            'a05 empty name' => ['A05', '', 'Coach'],
            'other dept' => ['A02', 'Consulting', 'Consulting'],
            'name only' => ['', 'Sales', 'Sales'],
        ];
    }
}
