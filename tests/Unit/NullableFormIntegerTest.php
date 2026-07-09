<?php

namespace Tests\Unit;

use App\Support\NullableFormInteger;
use PHPUnit\Framework\TestCase;

class NullableFormIntegerTest extends TestCase
{
    public function test_to_nullable_int_converts_empty_string_to_null(): void
    {
        $this->assertNull(NullableFormInteger::toNullableInt(''));
        $this->assertNull(NullableFormInteger::toNullableInt(null));
    }

    public function test_to_nullable_int_preserves_numeric_values(): void
    {
        $this->assertSame(3, NullableFormInteger::toNullableInt(3));
        $this->assertSame('3', NullableFormInteger::toNullableInt('3'));
    }

    public function test_normalize_payload_converts_known_integer_keys(): void
    {
        $normalized = NullableFormInteger::normalizePayload([
            'observe_unit' => '',
            'observe_lesson' => '',
            'session_number' => '',
            'support_purpose' => '정기 수업 참관',
        ]);

        $this->assertNull($normalized['observe_unit']);
        $this->assertNull($normalized['observe_lesson']);
        $this->assertNull($normalized['session_number']);
        $this->assertSame('정기 수업 참관', $normalized['support_purpose']);
    }
}
