<?php

namespace App\Jobs;

use App\Models\AccountInformation;
use App\Models\AssignmentChangeRequest;
use App\Models\ExternalAssignmentInboundLog;
use App\Models\Institution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessAssignmentChangeRequestsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function handle(): void
    {
        if (! (bool) config('services.assignment_sync.enabled', false)) {
            return;
        }

        AssignmentChangeRequest::query()
            ->where('origin', AssignmentChangeRequest::ORIGIN_EXTERNAL)
            ->where('status', AssignmentChangeRequest::STATUS_PENDING)
            ->orderBy('id')
            ->chunkById(100, function ($requests): void {
                $requests->each(fn (AssignmentChangeRequest $request): mixed => $this->applyRequest($request));
            });
    }

    private function applyRequest(AssignmentChangeRequest $request): void
    {
        try {
            DB::transaction(function () use ($request): void {
                $lockedRequest = AssignmentChangeRequest::query()
                    ->whereKey($request->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedRequest
                    || $lockedRequest->origin !== AssignmentChangeRequest::ORIGIN_EXTERNAL
                    || $lockedRequest->status !== AssignmentChangeRequest::STATUS_PENDING) {
                    return;
                }

                $accountInfo = AccountInformation::query()
                    ->where('SK_Code', $lockedRequest->sk_code)
                    ->first();

                if (! $accountInfo) {
                    throw new RuntimeException('대상 SK 코드의 담당자 마스터를 찾지 못했습니다.');
                }

                $before = [
                    'co' => $this->normalizeNullableString($accountInfo->CO),
                    'tr' => $this->normalizeNullableString($accountInfo->TR),
                    'cs' => $this->normalizeNullableString($accountInfo->CS),
                ];

                $after = [
                    'co' => $this->normalizeNullableString($lockedRequest->co),
                    'tr' => $this->normalizeNullableString($lockedRequest->tr),
                    'cs' => $this->normalizeNullableString($lockedRequest->cs),
                ];

                $patch = [];
                foreach ([
                    'co' => 'CO',
                    'tr' => 'TR',
                    'cs' => 'CS',
                ] as $logicalKey => $column) {
                    if ($after[$logicalKey] === null || $after[$logicalKey] === $before[$logicalKey]) {
                        continue;
                    }

                    $patch[$column] = $after[$logicalKey];
                }

                if ($patch !== []) {
                    $accountInfo->update($patch);
                }

                $institutionName = Institution::query()
                    ->where('SKcode', $lockedRequest->sk_code)
                    ->value('AccountName');

                ExternalAssignmentInboundLog::query()->create([
                    'sk_code' => $lockedRequest->sk_code,
                    'co' => $after['co'],
                    'tr' => $after['tr'],
                    'cs' => $after['cs'],
                    'raw_body' => [
                        'source' => 'assignment_change_request',
                        'origin' => AssignmentChangeRequest::ORIGIN_EXTERNAL,
                        'assignment_change_request_id' => $lockedRequest->id,
                        'institution_name' => $this->normalizeNullableString($institutionName),
                        'co' => $after['co'],
                        'tr' => $after['tr'],
                        'cs' => $after['cs'],
                        'before' => $before,
                        'changed_fields' => ['assignee'],
                    ],
                    'status' => 'applied',
                    'received_at' => $lockedRequest->requested_at ?? $lockedRequest->created_at ?? now(),
                    'applied_at' => now(),
                ]);

                $lockedRequest->update([
                    'status' => AssignmentChangeRequest::STATUS_APPLIED,
                    'applied_at' => now(),
                    'error_message' => null,
                ]);
            });
        } catch (Throwable $e) {
            $request->update([
                'status' => AssignmentChangeRequest::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::warning('assignment_change_request_apply_failed', [
                'id' => $request->id,
                'sk_code' => $request->sk_code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
