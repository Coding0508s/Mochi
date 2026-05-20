<?php

namespace App\Jobs;

use App\Enums\SyncOrigin;
use App\Models\Institution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SyncInstitutionOutboundJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $sk,
        public readonly SyncOrigin $origin,
    ) {}

    public function handle(): void
    {
        if ($this->origin === SyncOrigin::ExternalIngest) {
            return;
        }

        if (! (bool) config('services.institution_outbound.enabled')) {
            return;
        }

        $baseUrl = trim((string) config('services.institution_outbound.base_url', ''));
        $token = trim((string) config('services.institution_outbound.bearer_token', ''));

        if ($baseUrl === '' || $token === '') {
            return;
        }

        $institution = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $this->sk)
            ->first();

        if (! $institution) {
            return;
        }

        Http::withToken($token)
            ->acceptJson()
            ->timeout(10)
            ->put(rtrim($baseUrl, '/').'/internal/institutions/'.rawurlencode($this->sk), [
                'institution_name' => $institution->resolvedAccountName(),
                'co' => $institution->accountInfo?->CO,
                'tr' => $institution->accountInfo?->TR,
                'cs' => $institution->accountInfo?->CS,
                'temporary_sk' => $this->sk,
            ])
            ->throw();
    }
}
