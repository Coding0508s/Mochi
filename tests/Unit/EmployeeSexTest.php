<?php

namespace Tests\Unit;

use App\Support\EmployeeSex;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeSexTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function test_normalize_for_storage(?string $input, string $expected): void
    {
        $this->assertSame($expected, EmployeeSex::normalizeForStorage($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'unspecified null' => [null, ''],
            'unspecified empty' => ['', ''],
            'unspecified whitespace' => ['  ', ''],
            'male lowercase' => ['m', 'M'],
            'female' => ['F', 'F'],
            'invalid becomes unspecified' => ['X', ''],
        ];
    }
}
