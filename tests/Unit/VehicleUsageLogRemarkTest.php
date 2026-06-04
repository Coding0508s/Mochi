<?php

namespace Tests\Unit;

use App\Support\VehicleUsageLogRemark;
use PHPUnit\Framework\TestCase;

class VehicleUsageLogRemarkTest extends TestCase
{
    public function test_for_display_strips_excel_schedule_reference_tag(): void
    {
        $this->assertSame(
            'b2 09 / 양산 송원유치원, 대구 사랑유치원',
            VehicleUsageLogRemark::forDisplay('[excel-schedule:2026/05/28 -1] b2 09 / 양산 송원유치원, 대구 사랑유치원'),
        );
    }

    public function test_for_display_returns_empty_string_for_null_or_blank(): void
    {
        $this->assertSame('', VehicleUsageLogRemark::forDisplay(null));
        $this->assertSame('', VehicleUsageLogRemark::forDisplay('   '));
    }

    public function test_for_display_leaves_remarks_without_tag_unchanged(): void
    {
        $this->assertSame('기존 차량 적요', VehicleUsageLogRemark::forDisplay('기존 차량 적요'));
    }

    public function test_combine_arrival_and_reason_joins_with_slash(): void
    {
        $this->assertSame(
            '이천 어린왕자어린이집 / B2/ B16 이천 어린왕자어린이집',
            VehicleUsageLogRemark::combineArrivalAndReason(
                '이천 어린왕자어린이집',
                'B2/ B16 이천 어린왕자어린이집',
            ),
        );
    }

    public function test_combine_arrival_and_reason_returns_single_part_when_other_is_empty(): void
    {
        $this->assertSame('광명 올어바웃어린이집', VehicleUsageLogRemark::combineArrivalAndReason('', '광명 올어바웃어린이집'));
        $this->assertSame('광명 올어바웃어린이집', VehicleUsageLogRemark::combineArrivalAndReason('광명 올어바웃어린이집', ''));
    }
}
