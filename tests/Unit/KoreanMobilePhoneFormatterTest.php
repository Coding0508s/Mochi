<?php

namespace Tests\Unit;

use App\Support\KoreanMobilePhoneFormatter;
use PHPUnit\Framework\TestCase;

class KoreanMobilePhoneFormatterTest extends TestCase
{
    public function test_formats_eleven_digits_as_mobile_number(): void
    {
        $this->assertSame('010-1234-5678', KoreanMobilePhoneFormatter::format('01012345678'));
        $this->assertSame('010-1234-5678', KoreanMobilePhoneFormatter::format('010-1234-5678'));
        $this->assertSame('010-1234-5678', KoreanMobilePhoneFormatter::format('010 1234 5678'));
    }

    public function test_formats_partial_numbers_while_typing(): void
    {
        $this->assertSame('', KoreanMobilePhoneFormatter::format(null));
        $this->assertSame('010', KoreanMobilePhoneFormatter::format('010'));
        $this->assertSame('010-1', KoreanMobilePhoneFormatter::format('0101'));
        $this->assertSame('010-1234', KoreanMobilePhoneFormatter::format('0101234'));
        $this->assertSame('010-1234-5', KoreanMobilePhoneFormatter::format('01012345'));
    }

    public function test_caps_at_eleven_digits(): void
    {
        $this->assertSame('010-1234-5678', KoreanMobilePhoneFormatter::format('01012345678999'));
    }
}
