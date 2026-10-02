<?php

namespace Tests\Unit;

use App\Support\InstitutionSupportTimeSlots;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class InstitutionSupportTimeSlotsTest extends TestCase
{
    public function test_slots_are_thirty_minutes_apart(): void
    {
        $slots = InstitutionSupportTimeSlots::slots();

        $this->assertCount(48, $slots);
        $this->assertSame('00:00', $slots[0]);
        $this->assertSame('00:30', $slots[1]);
        $this->assertSame('23:30', $slots[47]);
        $this->assertTrue(InstitutionSupportTimeSlots::isSlot('14:30'));
        $this->assertFalse(InstitutionSupportTimeSlots::isSlot('14:20'));
    }

    public function test_nearest_rounds_to_the_closest_half_hour(): void
    {
        $this->assertSame('14:00', InstitutionSupportTimeSlots::nearest(Carbon::parse('2026-10-02 14:10:00')));
        $this->assertSame('14:30', InstitutionSupportTimeSlots::nearest(Carbon::parse('2026-10-02 14:20:00')));
        $this->assertSame('15:00', InstitutionSupportTimeSlots::nearest(Carbon::parse('2026-10-02 14:45:00')));
        $this->assertSame('00:00', InstitutionSupportTimeSlots::nearest(Carbon::parse('2026-10-02 23:50:00')));
    }

    public function test_accepts_half_hours_and_a_saved_time_that_is_being_kept(): void
    {
        $this->assertTrue(InstitutionSupportTimeSlots::accepts(null));
        $this->assertTrue(InstitutionSupportTimeSlots::accepts(''));
        $this->assertTrue(InstitutionSupportTimeSlots::accepts('18:30'));
        $this->assertTrue(InstitutionSupportTimeSlots::accepts('18:30:00'));
        $this->assertFalse(InstitutionSupportTimeSlots::accepts('18:23'));
        $this->assertTrue(InstitutionSupportTimeSlots::accepts('18:23', '18:23:00'));
        $this->assertFalse(InstitutionSupportTimeSlots::accepts('18:23', '18:00'));
    }

    public function test_options_keep_a_saved_time_that_is_not_on_a_slot(): void
    {
        $options = InstitutionSupportTimeSlots::optionsIncluding('11:20:00');

        $this->assertContains('11:20', $options);
        $this->assertContains('11:00', $options);
        $this->assertContains('11:30', $options);
        $this->assertSame(
            ['11:00', '11:20', '11:30'],
            array_values(array_filter(
                $options,
                fn (string $option): bool => str_starts_with($option, '11:'),
            )),
        );
    }
}
