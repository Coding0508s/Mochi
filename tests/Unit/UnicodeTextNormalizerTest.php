<?php

namespace Tests\Unit;

use App\Support\UnicodeTextNormalizer;
use Normalizer;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

class UnicodeTextNormalizerTest extends TestCase
{
    #[RequiresPhpExtension('intl')]
    public function test_converts_nfd_korean_to_nfc(): void
    {
        $nfc = 'GrapeSEED 스프링 노트(10권)';
        $nfd = Normalizer::normalize($nfc, Normalizer::FORM_D);
        $this->assertNotSame($nfc, $nfd);

        $this->assertSame($nfc, UnicodeTextNormalizer::toNfc($nfd));
    }

    public function test_returns_empty_string_for_null_or_empty(): void
    {
        $this->assertSame('', UnicodeTextNormalizer::toNfc(null));
        $this->assertSame('', UnicodeTextNormalizer::toNfc(''));
    }

    public function test_preserves_ascii_text(): void
    {
        $this->assertSame('Mr.Lineman', UnicodeTextNormalizer::toNfc('Mr.Lineman'));
    }
}
