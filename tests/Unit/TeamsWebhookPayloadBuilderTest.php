<?php

namespace Tests\Unit;

use App\GsBrochure\Services\TeamsWebhookPayloadBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamsWebhookPayloadBuilderTest extends TestCase
{
    private TeamsWebhookPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new TeamsWebhookPayloadBuilder;
    }

    #[DataProvider('workflowsWebhookUrlProvider')]
    public function test_builds_adaptive_card_payload_for_workflows_webhook_urls(string $webhookUrl): void
    {
        $payload = $this->builder->buildBrochureRequestPayload(
            webhookUrl: $webhookUrl,
            format: TeamsWebhookPayloadBuilder::FORMAT_AUTO,
            requesterName: '홍길동',
            schoolname: '테스트 기관',
            phone: '010-1234-5678',
            address: '서울',
            requestDate: '2026-06-23',
            brochureFacts: [['name' => '브로셔 A', 'value' => '20권']],
            logisticsUrl: 'https://example.test/logistics',
        );

        $this->assertSame('AdaptiveCard', $payload['attachments'][0]['content']['type'] ?? null);
        $this->assertSame('1.4', $payload['attachments'][0]['content']['version'] ?? null);
        $this->assertSame('message', $payload['type'] ?? null);
        $this->assertSame('홍길동', $this->adaptiveFactValue($payload['attachments'][0]['content'], '신청자'));
        $this->assertSame('Action.OpenUrl', $payload['attachments'][0]['content']['actions'][0]['type'] ?? null);
        $this->assertSame('https://example.test/logistics', $payload['attachments'][0]['content']['actions'][0]['url'] ?? null);
    }

    public function test_builds_message_card_payload_for_incoming_webhook_urls(): void
    {
        $payload = $this->builder->buildBrochureRequestPayload(
            webhookUrl: 'https://example.webhook.office.com/webhookb2/abc',
            format: TeamsWebhookPayloadBuilder::FORMAT_AUTO,
            requesterName: '홍길동',
            schoolname: '테스트 기관',
            phone: '010-1234-5678',
            address: '서울',
            requestDate: '2026-06-23',
            brochureFacts: [['name' => '브로셔 A', 'value' => '20권']],
            logisticsUrl: 'https://example.test/logistics',
        );

        $this->assertSame('MessageCard', $payload['@type'] ?? null);
        $this->assertSame('홍길동', $payload['sections'][0]['facts'][0]['value'] ?? null);
        $this->assertSame('OpenUri', $payload['potentialAction'][0]['@type'] ?? null);
    }

    public function test_format_override_can_force_adaptive_card(): void
    {
        $payload = $this->builder->buildInvoicePayload(
            webhookUrl: 'https://example.webhook.office.com/webhookb2/abc',
            format: TeamsWebhookPayloadBuilder::FORMAT_ADAPTIVE_CARD,
            requesterName: '외부 신청자',
            schoolname: '테스트 기관',
            requestDate: '2026-06-23',
            invoiceFacts: [['name' => '운송장 번호', 'value' => '1234567890']],
        );

        $this->assertSame('AdaptiveCard', $payload['attachments'][0]['content']['type'] ?? null);
        $this->assertSame('1234567890', $this->adaptiveFactValue($payload['attachments'][0]['content'], '운송장 번호'));
    }

    public function test_builds_store_return_registration_payload_for_incoming_webhook(): void
    {
        $payload = $this->builder->buildStoreReturnRegistrationPayload(
            webhookUrl: 'https://example.webhook.office.com/webhookb2/abc',
            format: TeamsWebhookPayloadBuilder::FORMAT_AUTO,
            registrantName: 'Logistics User',
            returnedAt: '2026-07-10',
            institutionName: '포도씨 유치원',
            institutionSkCode: 'SK1001',
            freight: '선불',
            csTeam: 'Ellen Joo',
            itemFacts: [['name' => 'Unit 4', 'value' => '2개 / 정상 / 스티커']],
            returnsUrl: 'https://example.test/store/returns?team_menu=cs',
        );

        $this->assertSame('MessageCard', $payload['@type'] ?? null);
        $this->assertSame('물류 반품 등록', $payload['summary'] ?? null);
        $this->assertSame('포도씨 유치원', $payload['sections'][0]['facts'][2]['value'] ?? null);
        $this->assertSame('Unit 4', $payload['sections'][1]['facts'][0]['name'] ?? null);
        $this->assertSame('반품 등록 보기', $payload['potentialAction'][0]['name'] ?? null);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function workflowsWebhookUrlProvider(): array
    {
        return [
            'logic azure' => ['https://prod-01.koreacentral.logic.azure.com/workflows/abc/triggers/manual/paths/invoke'],
            'power platform' => ['https://default.environment.api.powerplatform.com/powerautomate/abc'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function adaptiveFactValue(array $payload, string $title): ?string
    {
        foreach ($payload['body'] ?? [] as $block) {
            if (($block['type'] ?? null) !== 'FactSet') {
                continue;
            }

            foreach ($block['facts'] ?? [] as $fact) {
                if (($fact['title'] ?? null) === $title) {
                    return $fact['value'] ?? null;
                }
            }
        }

        return null;
    }
}
