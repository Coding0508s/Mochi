<?php

namespace Tests\Unit;

use App\Support\MultilineTextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MultilineTextNormalizerTest extends TestCase
{
    public function test_trims_leading_and_trailing_whitespace_per_line(): void
    {
        $input = "  1. 방문 목적  \n    ▶ 신규 도입 상담\n  2. 미팅 내용  \n▶ 기관 개요.";

        $this->assertSame(
            "1. 방문 목적\n▶ 신규 도입 상담\n2. 미팅 내용\n▶ 기관 개요.",
            MultilineTextNormalizer::normalize($input),
        );
    }

    public function test_normalizes_full_width_spaces_and_tabs(): void
    {
        $input = "\u{3000}제목\u{3000}\n\t▶\t내용";

        $this->assertSame(
            "제목\n▶ 내용",
            MultilineTextNormalizer::normalize($input),
        );
    }

    public function test_strips_non_breaking_space_padding_per_line(): void
    {
        $input = "\u{00A0}\u{00A0}1. 방문목적\u{00A0}\n\u{00A0}▶ 도입상담";

        $this->assertSame(
            "1. 방문목적\n▶ 도입상담",
            MultilineTextNormalizer::normalize($input),
        );
    }

    public function test_strips_zero_width_and_bom_characters_per_line(): void
    {
        $input = "\u{FEFF}\u{200B}1. 방문목적\u{200D}\n\u{2060}▶ 도입상담";

        $this->assertSame(
            "1. 방문목적\n▶ 도입상담",
            MultilineTextNormalizer::normalize($input),
        );
    }

    public function test_preserves_blank_line_between_paragraphs(): void
    {
        $input = "첫 줄\n\n둘째 줄";

        $this->assertSame("첫 줄\n\n둘째 줄", MultilineTextNormalizer::normalize($input));
    }

    public function test_empty_string_becomes_null(): void
    {
        $this->assertNull(MultilineTextNormalizer::normalize(''));
        $this->assertNull(MultilineTextNormalizer::normalize("  \n  \n  "));
    }

    #[DataProvider('normalizedLineAlignmentProvider')]
    public function test_short_title_lines_start_at_left_without_padding_spaces(string $input, string $expectedFirstLine): void
    {
        $normalized = MultilineTextNormalizer::normalize($input);

        $this->assertNotNull($normalized);
        $this->assertSame($expectedFirstLine, explode("\n", $normalized, 2)[0]);
        $this->assertStringStartsNotWith(' ', $normalized);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizedLineAlignmentProvider(): array
    {
        return [
            'centered title padding' => ["     1. 방문 목적     \n▶ 항목", '1. 방문 목적'],
            'ideographic space padding' => ["\u{3000}\u{3000}2. 미팅 내용\u{3000}\u{3000}\n▶ 항목", '2. 미팅 내용'],
        ];
    }
}
