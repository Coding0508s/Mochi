<?php

namespace App\GsBrochure\Services;

class TeamsWebhookPayloadBuilder
{
    public const FORMAT_AUTO = 'auto';

    public const FORMAT_MESSAGE_CARD = 'messagecard';

    public const FORMAT_ADAPTIVE_CARD = 'adaptive';

    /**
     * @param  list<array{name: string, value: string}>  $brochureFacts
     */
    public function buildBrochureRequestPayload(
        string $webhookUrl,
        string $format,
        string $requesterName,
        string $schoolname,
        string $phone,
        string $address,
        string $requestDate,
        array $brochureFacts,
        string $logisticsUrl,
    ): array {
        if ($this->usesAdaptiveCard($webhookUrl, $format)) {
            return $this->buildWorkflowsMessageEnvelope($this->buildAdaptiveCardPayload(
                title: '브로셔 발송 요청',
                factSections: [
                    [
                        'facts' => [
                            ['title' => '신청자', 'value' => $requesterName],
                            ['title' => '기관명', 'value' => $schoolname],
                            ['title' => '연락처', 'value' => $phone],
                            ['title' => '주소', 'value' => $address],
                            ['title' => '신청일', 'value' => $requestDate],
                        ],
                    ],
                    [
                        'heading' => '발송 브로셔 목록',
                        'facts' => $this->messageCardFactsToAdaptiveFacts($brochureFacts),
                    ],
                ],
                actions: [[
                    'type' => 'Action.OpenUrl',
                    'title' => '운송장 입력',
                    'url' => $logisticsUrl,
                ]],
            ));
        }

        return [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => '590091',
            'summary' => '브로셔 발송 요청',
            'sections' => [[
                'markdown' => true,
                'activityTitle' => '**브로셔 발송 요청**',
                'facts' => [
                    ['name' => '신청자', 'value' => $requesterName],
                    ['name' => '기관명', 'value' => $schoolname],
                    ['name' => '연락처', 'value' => $phone],
                    ['name' => '주소', 'value' => $address],
                    ['name' => '신청일', 'value' => $requestDate],
                ],
            ], [
                'activityTitle' => '**발송 브로셔 목록**',
                'facts' => $brochureFacts,
            ]],
            'potentialAction' => [[
                '@type' => 'OpenUri',
                'name' => '운송장 입력',
                'targets' => [[
                    'os' => 'default',
                    'uri' => $logisticsUrl,
                ]],
            ]],
        ];
    }

    /**
     * @param  list<array{name: string, value: string}>  $invoiceFacts
     */
    public function buildInvoicePayload(
        string $webhookUrl,
        string $format,
        string $requesterName,
        string $schoolname,
        string $requestDate,
        array $invoiceFacts,
    ): array {
        if ($this->usesAdaptiveCard($webhookUrl, $format)) {
            return $this->buildWorkflowsMessageEnvelope($this->buildAdaptiveCardPayload(
                title: '운송장 등록 완료 (물류창고)',
                factSections: [
                    [
                        'facts' => [
                            ['title' => '신청자', 'value' => $requesterName],
                            ['title' => '기관명', 'value' => $schoolname],
                            ['title' => '신청일', 'value' => $requestDate],
                        ],
                    ],
                    [
                        'heading' => '등록된 운송장 번호',
                        'facts' => $this->messageCardFactsToAdaptiveFacts($invoiceFacts),
                    ],
                ],
            ));
        }

        return [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => '28a745',
            'summary' => '운송장 등록 완료 (물류창고)',
            'sections' => [[
                'activityTitle' => '**운송장 등록 완료** (물류창고)',
                'facts' => [
                    ['name' => '신청자', 'value' => $requesterName],
                    ['name' => '기관명', 'value' => $schoolname],
                    ['name' => '신청일', 'value' => $requestDate],
                ],
            ], [
                'activityTitle' => '**등록된 운송장 번호**',
                'facts' => $invoiceFacts,
            ]],
        ];
    }

    /**
     * @param  list<array{name: string, value: string}>  $itemFacts
     */
    public function buildStoreReturnRegistrationPayload(
        string $webhookUrl,
        string $format,
        string $registrantName,
        string $returnedAt,
        string $institutionName,
        string $institutionSkCode,
        string $freight,
        string $csTeam,
        array $itemFacts,
        string $returnsUrl,
    ): array {
        $summaryFacts = [
            ['name' => '등록자', 'value' => $registrantName],
            ['name' => 'Date', 'value' => $returnedAt],
            ['name' => '기관명', 'value' => $institutionName],
            ['name' => 'SK 코드', 'value' => $institutionSkCode !== '' ? $institutionSkCode : '-'],
            ['name' => '운임', 'value' => $freight !== '' ? $freight : '-'],
            ['name' => '담당 CS 팀', 'value' => $csTeam !== '' ? $csTeam : '-'],
        ];

        if ($this->usesAdaptiveCard($webhookUrl, $format)) {
            return $this->buildWorkflowsMessageEnvelope($this->buildAdaptiveCardPayload(
                title: '물류 반품 등록',
                factSections: [
                    [
                        'facts' => $this->messageCardFactsToAdaptiveFacts($summaryFacts),
                    ],
                    [
                        'heading' => '반품 품목',
                        'facts' => $this->messageCardFactsToAdaptiveFacts($itemFacts),
                    ],
                ],
                actions: [[
                    'type' => 'Action.OpenUrl',
                    'title' => '반품 등록 보기',
                    'url' => $returnsUrl,
                ]],
            ));
        }

        return [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => '2b78c5',
            'summary' => '물류 반품 등록',
            'sections' => [[
                'activityTitle' => '**물류 반품 등록**',
                'facts' => $summaryFacts,
            ], [
                'activityTitle' => '**반품 품목**',
                'facts' => $itemFacts,
            ]],
            'potentialAction' => [[
                '@type' => 'OpenUri',
                'name' => '반품 등록 보기',
                'targets' => [[
                    'os' => 'default',
                    'uri' => $returnsUrl,
                ]],
            ]],
        ];
    }

    /**
     * @param  list<array{name: string, value: string}>  $itemFacts
     */
    public function buildStoreReturnCompletionPayload(
        string $webhookUrl,
        string $format,
        string $message,
        string $completedByName,
        string $returnedAt,
        string $institutionName,
        string $institutionSkCode,
        string $freight,
        string $csTeam,
        array $itemFacts,
        string $returnsUrl,
    ): array {
        $summaryFacts = [
            ['name' => '처리자', 'value' => $completedByName],
            ['name' => 'Date', 'value' => $returnedAt],
            ['name' => '기관명', 'value' => $institutionName],
            ['name' => 'SK 코드', 'value' => $institutionSkCode !== '' ? $institutionSkCode : '-'],
            ['name' => '운임', 'value' => $freight !== '' ? $freight : '-'],
            ['name' => '담당 CS 팀', 'value' => $csTeam !== '' ? $csTeam : '-'],
        ];

        if ($this->usesAdaptiveCard($webhookUrl, $format)) {
            return $this->buildWorkflowsMessageEnvelope($this->buildAdaptiveCardPayload(
                title: '반품 처리 완료',
                factSections: [
                    [
                        'facts' => $this->messageCardFactsToAdaptiveFacts($summaryFacts),
                    ],
                    [
                        'heading' => '반품 품목',
                        'facts' => $this->messageCardFactsToAdaptiveFacts($itemFacts),
                    ],
                ],
                actions: [[
                    'type' => 'Action.OpenUrl',
                    'title' => '반품 현황 보기',
                    'url' => $returnsUrl,
                ]],
                introText: $message,
            ));
        }

        return [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => '28a745',
            'summary' => '반품 처리 완료',
            'sections' => [[
                'activityTitle' => '**반품 처리 완료**',
                'text' => $message,
                'facts' => $summaryFacts,
            ], [
                'activityTitle' => '**반품 품목**',
                'facts' => $itemFacts,
            ]],
            'potentialAction' => [[
                '@type' => 'OpenUri',
                'name' => '반품 현황 보기',
                'targets' => [[
                    'os' => 'default',
                    'uri' => $returnsUrl,
                ]],
            ]],
        ];
    }

    public function usesAdaptiveCard(string $webhookUrl, string $format = self::FORMAT_AUTO): bool
    {
        $normalizedFormat = strtolower(trim($format));

        return match ($normalizedFormat) {
            self::FORMAT_ADAPTIVE_CARD => true,
            self::FORMAT_MESSAGE_CARD => false,
            default => $this->isWorkflowsWebhookUrl($webhookUrl),
        };
    }

    public function isWorkflowsWebhookUrl(string $webhookUrl): bool
    {
        $host = strtolower((string) parse_url($webhookUrl, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        foreach ([
            'logic.azure.com',
            'powerplatform.com',
            'powerautomate.com',
            'flow.microsoft.com',
            'api.powerplatform.com',
        ] as $workflowsHostSuffix) {
            if ($host === $workflowsHostSuffix || str_ends_with($host, '.'.$workflowsHostSuffix)) {
                return true;
            }
        }

        return false;
    }

    public function isIncomingWebhookUrl(string $webhookUrl): bool
    {
        $host = strtolower((string) parse_url($webhookUrl, PHP_URL_HOST));

        return $host === 'webhook.office.com'
            || str_ends_with($host, '.webhook.office.com');
    }

    /**
     * @param  list<array{heading?: string, facts: list<array{title: string, value: string}>}>  $factSections
     * @param  list<array{type: string, title: string, url: string}>  $actions
     * @return array<string, mixed>
     */
    private function buildAdaptiveCardPayload(string $title, array $factSections, array $actions = [], ?string $introText = null): array
    {
        $body = [[
            'type' => 'TextBlock',
            'text' => $title,
            'weight' => 'Bolder',
            'size' => 'Medium',
            'wrap' => true,
        ]];

        if (filled($introText)) {
            $body[] = [
                'type' => 'TextBlock',
                'text' => $introText,
                'wrap' => true,
                'spacing' => 'Small',
            ];
        }

        foreach ($factSections as $section) {
            if (($section['heading'] ?? '') !== '') {
                $body[] = [
                    'type' => 'TextBlock',
                    'text' => $section['heading'],
                    'weight' => 'Bolder',
                    'wrap' => true,
                    'spacing' => 'Medium',
                ];
            }

            $body[] = [
                'type' => 'FactSet',
                'facts' => $section['facts'],
            ];
        }

        $card = [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'type' => 'AdaptiveCard',
            'version' => '1.4',
            'body' => $body,
        ];

        if ($actions !== []) {
            $card['actions'] = $actions;
        }

        return $card;
    }

    /**
     * Teams Workflows 웹훅 트리거가 기대하는 message + attachments 래퍼.
     *
     * @param  array<string, mixed>  $adaptiveCard
     * @return array<string, mixed>
     */
    public function buildWorkflowsMessageEnvelope(array $adaptiveCard): array
    {
        return [
            'type' => 'message',
            'attachments' => [[
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'contentUrl' => null,
                'content' => $adaptiveCard,
            ]],
        ];
    }

    /**
     * @param  list<array{name: string, value: string}>  $facts
     * @return list<array{title: string, value: string}>
     */
    private function messageCardFactsToAdaptiveFacts(array $facts): array
    {
        return array_map(
            fn (array $fact): array => [
                'title' => (string) ($fact['name'] ?? '-'),
                'value' => (string) ($fact['value'] ?? '-'),
            ],
            $facts,
        );
    }
}
