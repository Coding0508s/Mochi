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
            'TO_Account' => "사전 요청 및 주요 이슈\n사전 요청 본문\n\n세부 지원 내용\n모니터링 결과",
        ]);

        $html = SupportReportMailBodyFormatter::supportContentHtml($record, 'teacher');

        $this->assertStringContainsString('color:#0942a3', $html);
        $this->assertStringContainsString('사전 요청 및 주요 이슈', $html);
        $this->assertStringContainsString('세부 지원 내용', $html);
        $this->assertStringContainsString('모니터링 결과', $html);
        $this->assertStringContainsString('font-weight:bold', $html);
    }

    public function test_institution_support_content_stays_plain_text(): void
    {
        $record = new SupportRecord([
            'Support_Type' => '전화',
            'TO_Account' => "기관 이슈\n논의 내용",
        ]);

        $html = SupportReportMailBodyFormatter::supportContentHtml($record, 'institution');

        $this->assertStringNotContainsString('color:#0942a3', $html);
        $this->assertStringContainsString('기관 이슈', $html);
    }
}
