<?php

namespace Tests\Unit;

use App\Models\SupportRecord;
use App\Support\SupportReportMailBodyFormatter;
use Tests\TestCase;

class SupportReportMailBodyFormatterTest extends TestCase
{
    public function test_visit_support_content_renders_colored_section_headers(): void
    {
        $record = new SupportRecord([
            'Support_Type' => '교사 지원 및 참관',
            'TO_Account' => "사전 요청 및 주요 이슈\n사전 요청 본문\n후속 요청\n\n세부 지원 내용\n모니터링 결과",
        ]);

        $html = SupportReportMailBodyFormatter::supportContentHtml($record, 'teacher');

        $this->assertStringContainsString('color:#0942a3', $html);
        $this->assertStringContainsString('사전 요청 및 주요 이슈', $html);
        $this->assertStringContainsString('세부 지원 내용', $html);
        $this->assertStringContainsString('모니터링 결과', $html);
        $this->assertStringContainsString('font-weight:bold', $html);
        $this->assertStringContainsString("사전 요청 본문<br>\n", $html);
    }

    public function test_institution_support_content_converts_line_breaks_to_br_tags(): void
    {
        $record = new SupportRecord([
            'Support_Type' => '전화',
            'TO_Account' => "기관 이슈\n논의 내용<script>alert(1)</script>",
        ]);

        $html = SupportReportMailBodyFormatter::supportContentHtml($record, 'institution');

        $this->assertStringNotContainsString('color:#0942a3', $html);
        $this->assertStringContainsString("기관 이슈<br>\n논의 내용", $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_text_with_line_breaks_uses_placeholder_for_empty_text(): void
    {
        $this->assertSame('—', SupportReportMailBodyFormatter::textWithLineBreaks(null));
        $this->assertSame('—', SupportReportMailBodyFormatter::textWithLineBreaks(''));
    }
}
