<?php

namespace App\Support;

use App\GsBrochure\Services\TeamsWebhookPayloadBuilder;
use Illuminate\Support\Facades\Http;

final class StoreReturnTeamsNotifier
{
    public const COMPLETION_MESSAGE = '반품 처리 완료. 이카운트 전표 확인 바랍니다.';

    public function __construct(
        private readonly TeamsWebhookPayloadBuilder $payloadBuilder,
    ) {}

    /**
     * @param  array{
     *     returned_at: string,
     *     institution_name: string,
     *     institution_sk_code: ?string,
     *     freight: ?string,
     *     cs_team: ?string,
     *     registrant_name: string,
     *     items: list<array{
     *         item_name: string,
     *         quantity: int,
     *         status: string,
     *         notes: ?string
     *     }>
     * }  $registration
     */
    public function notifyRegistered(array $registration): void
    {
        $webhookUrl = config('services.store_return_teams.webhook_url');
        if (! is_string($webhookUrl) || $webhookUrl === '') {
            return;
        }

        try {
            $itemFacts = collect($registration['items'])
                ->map(function (array $item): array {
                    $value = number_format($item['quantity']).'개 / '.$item['status'];
                    if (filled($item['notes'] ?? null)) {
                        $value .= ' / '.$item['notes'];
                    }

                    return [
                        'name' => $item['item_name'],
                        'value' => $value,
                    ];
                })
                ->values()
                ->all();

            $payload = $this->payloadBuilder->buildStoreReturnRegistrationPayload(
                webhookUrl: $webhookUrl,
                format: (string) config('services.store_return_teams.webhook_format', TeamsWebhookPayloadBuilder::FORMAT_AUTO),
                registrantName: $registration['registrant_name'],
                returnedAt: $registration['returned_at'],
                institutionName: $registration['institution_name'],
                institutionSkCode: (string) ($registration['institution_sk_code'] ?? ''),
                freight: (string) ($registration['freight'] ?? ''),
                csTeam: (string) ($registration['cs_team'] ?? ''),
                itemFacts: $itemFacts,
                returnsUrl: $this->resolveReturnsUrl(),
            );

            Http::timeout(5)->post($webhookUrl, $payload);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array{
     *     returned_at: string,
     *     institution_name: string,
     *     institution_sk_code: ?string,
     *     freight: ?string,
     *     cs_team: ?string,
     *     completed_by_name: string,
     *     items: list<array{
     *         item_name: string,
     *         quantity: int,
     *         status: string,
     *         notes: ?string
     *     }>
     * }  $registration
     */
    public function notifyCompleted(array $registration): void
    {
        $webhookUrl = config('services.store_return_teams.webhook_url');
        if (! is_string($webhookUrl) || $webhookUrl === '') {
            return;
        }

        try {
            $itemFacts = collect($registration['items'])
                ->map(function (array $item): array {
                    $value = number_format($item['quantity']).'개 / '.$item['status'];
                    if (filled($item['notes'] ?? null)) {
                        $value .= ' / '.$item['notes'];
                    }

                    return [
                        'name' => $item['item_name'],
                        'value' => $value,
                    ];
                })
                ->values()
                ->all();

            $payload = $this->payloadBuilder->buildStoreReturnCompletionPayload(
                webhookUrl: $webhookUrl,
                format: (string) config('services.store_return_teams.webhook_format', TeamsWebhookPayloadBuilder::FORMAT_AUTO),
                message: self::COMPLETION_MESSAGE,
                completedByName: $registration['completed_by_name'],
                returnedAt: $registration['returned_at'],
                institutionName: $registration['institution_name'],
                institutionSkCode: (string) ($registration['institution_sk_code'] ?? ''),
                freight: (string) ($registration['freight'] ?? ''),
                csTeam: (string) ($registration['cs_team'] ?? ''),
                itemFacts: $itemFacts,
                returnsUrl: $this->resolveLogisticsReturnsUrl(),
            );

            Http::timeout(5)->post($webhookUrl, $payload);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function resolveReturnsUrl(): string
    {
        $configuredUrl = config('services.store_return_teams.returns_url');
        if (is_string($configuredUrl) && $configuredUrl !== '') {
            return $configuredUrl;
        }

        return route('store.returns.index', ['team_menu' => TeamMenuContext::MENU_CS]);
    }

    private function resolveLogisticsReturnsUrl(): string
    {
        $configuredUrl = config('services.store_return_teams.returns_url');
        if (is_string($configuredUrl) && $configuredUrl !== '') {
            return $configuredUrl;
        }

        return route('store.returns.index', ['team_menu' => TeamMenuContext::MENU_LOGISTICS]);
    }
}
