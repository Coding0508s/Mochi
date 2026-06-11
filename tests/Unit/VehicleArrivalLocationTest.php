<?php

namespace Tests\Unit;

use App\Support\VehicleArrivalLocation;
use PHPUnit\Framework\TestCase;

class VehicleArrivalLocationTest extends TestCase
{
    public function test_compose_builds_floor_pillar_number_format(): void
    {
        $this->assertSame('B2 / B29', VehicleArrivalLocation::compose('B2', 'B', '29'));
    }

    public function test_for_display_normalizes_structured_values_with_slash(): void
    {
        $this->assertSame('B2 / B29', VehicleArrivalLocation::forDisplay('B2 B29'));
        $this->assertSame('B2 / B16', VehicleArrivalLocation::forDisplay('B2/ B16'));
        $this->assertSame('창의업유치원', VehicleArrivalLocation::forDisplay('창의업유치원'));
    }

    public function test_parse_reads_spaced_and_slash_formats(): void
    {
        $this->assertSame(
            ['floor' => 'B2', 'pillar' => 'B', 'number' => '29'],
            VehicleArrivalLocation::parse('B2 B29'),
        );

        $this->assertSame(
            ['floor' => 'B2', 'pillar' => 'B', 'number' => '16'],
            VehicleArrivalLocation::parse('B2/ B16'),
        );
    }

    public function test_parse_returns_empty_parts_for_legacy_free_text(): void
    {
        $this->assertSame(
            ['floor' => '', 'pillar' => '', 'number' => ''],
            VehicleArrivalLocation::parse('창의업유치원'),
        );
    }
}
