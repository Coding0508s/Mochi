<?php

namespace App\Jobs;

use App\Models\AccountInformation;
use App\Models\CoNewTarget;
use App\Models\ExternalAssignmentInboundLog;
use App\Models\Institution;
use App\Models\SkCodeRequest;
use App\Services\PotentialInstitutionSkCodeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessSkCodeRequestsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function handle(PotentialInstitutionSkCodeService $skCodeService): void
    {
        SkCodeRequest::query()
            ->where('status', 'completed')
            ->whereNotNull('final_sk_code')
            ->where(function ($query): void {
                $query
                    ->whereNull('applied_at')
                    ->orWhereColumn('updated_at', '>', 'applied_at');
            })
            ->orderBy('id')
            ->chunkById(100, function ($requests) use ($skCodeService): void {
                $requests->each(fn (SkCodeRequest $req): mixed => $this->applyRequest($req, $skCodeService));
            });
    }

    private function applyRequest(SkCodeRequest $req, PotentialInstitutionSkCodeService $skCodeService): void
    {
        try {
            DB::transaction(function () use ($req, $skCodeService): void {
                $lockedReq = SkCodeRequest::query()
                    ->whereKey($req->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedReq || $lockedReq->status !== 'completed' || $lockedReq->final_sk_code === null) {
                    return;
                }

                if ($lockedReq->applied_at !== null && ! $lockedReq->updated_at?->gt($lockedReq->applied_at)) {
                    return;
                }

                $didRenameSk = false;
                if ($lockedReq->applied_at === null && ! $this->finalInstitutionExists($lockedReq)) {
                    $skCodeService->renameInstitutionSk(
                        $lockedReq->temp_sk_code,
                        (string) $lockedReq->final_sk_code
                    );
                    $didRenameSk = true;
                }

                $before = $this->currentAppliedValues($lockedReq);

                $this->syncInstitutionFields($lockedReq);
                $this->syncAccountInformationAssignees($lockedReq);

                // 기존 벨 알림 인프라를 재사용해 상단 벨에 알림 표시
                ExternalAssignmentInboundLog::create([
                    'sk_code' => (string) $lockedReq->final_sk_code,
                    'co' => $this->nullableTrimmedString($lockedReq->co),
                    'tr' => $this->nullableTrimmedString($lockedReq->tr),
                    'cs' => $this->nullableTrimmedString($lockedReq->cs),
                    'raw_body' => [
                        'source' => 'sk_code_request',
                        'sk_code_request_id' => $lockedReq->id,
                        'institution_name' => $lockedReq->institution_name,
                        'replaces_sk' => $didRenameSk ? $lockedReq->temp_sk_code : null,
                        'portal_campus_id' => $lockedReq->portal_campus_id,
                        'account_no' => $lockedReq->account_no,
                        'co' => $lockedReq->co,
                        'tr' => $lockedReq->tr,
                        'cs' => $lockedReq->cs,
                        'changed_fields' => $this->changedFields($lockedReq),
                        'before' => $before,
                    ],
                    'status' => 'applied',
                    'received_at' => now(),
                    'applied_at' => now(),
                ]);

                $lockedReq->update([
                    'applied_at' => now(),
                    'error_message' => null,
                ]);
            });
        } catch (Throwable $e) {
            $req->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::warning('sk_code_request_apply_failed', [
                'id' => $req->id,
                'temp_sk_code' => $req->temp_sk_code,
                'final_sk_code' => $req->final_sk_code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncInstitutionFields(SkCodeRequest $req): void
    {
        $patch = [];

        foreach ([
            'institution_name' => 'AccountName',
            'portal_campus_id' => 'PortalCampusID',
            'account_no' => 'AccountNo',
        ] as $requestColumn => $institutionColumn) {
            $value = $req->{$requestColumn};
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $patch[$institutionColumn] = trim((string) $value);
        }

        if ($patch === []) {
            return;
        }

        $institution = Institution::query()
            ->where('SKcode', (string) $req->final_sk_code)
            ->first();

        if (! $institution) {
            throw new RuntimeException('확정 SK 기관에 포털/사업자 정보를 반영하지 못했습니다.');
        }

        $institution->update($patch);

        if (array_key_exists('AccountName', $patch)) {
            // 행이 없으면 update()는 0건이라 Account_Name 이 전혀 반영되지 않음 → upsert 와 동일하게 맞춤
            AccountInformation::query()->updateOrCreate(
                ['SK_Code' => (string) $req->final_sk_code],
                ['Account_Name' => $patch['AccountName']]
            );

            if ($req->co_new_target_id !== null) {
                CoNewTarget::query()
                    ->whereKey((int) $req->co_new_target_id)
                    ->update(['AccountName' => $patch['AccountName']]);
            }
        }
    }

    private function finalInstitutionExists(SkCodeRequest $req): bool
    {
        return Institution::query()
            ->where('SKcode', (string) $req->final_sk_code)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function currentAppliedValues(SkCodeRequest $req): array
    {
        $institution = Institution::query()
            ->where('SKcode', (string) $req->final_sk_code)
            ->first(['AccountName', 'PortalCampusID', 'AccountNo']);
        $accountInfo = AccountInformation::query()
            ->where('SK_Code', (string) $req->final_sk_code)
            ->first(['Account_Name', 'CO', 'TR', 'CS']);

        return [
            'institution_name' => $institution?->AccountName,
            'account_name' => $accountInfo?->Account_Name,
            'portal_campus_id' => $institution?->PortalCampusID,
            'account_no' => $institution?->AccountNo,
            'co' => $accountInfo?->CO,
            'tr' => $accountInfo?->TR,
            'cs' => $accountInfo?->CS,
        ];
    }

    /**
     * S_Account_Information 의 CO / TR / CS (담당자). 비어 있으면 건너뜀.
     */
    private function syncAccountInformationAssignees(SkCodeRequest $req): void
    {
        $patch = [];

        foreach ([
            'co' => 'CO',
            'tr' => 'TR',
            'cs' => 'CS',
        ] as $requestColumn => $dbColumn) {
            $value = $req->{$requestColumn};
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $patch[$dbColumn] = trim((string) $value);
        }

        if ($patch === []) {
            return;
        }

        $accountInfo = AccountInformation::query()
            ->where('SK_Code', (string) $req->final_sk_code)
            ->first();

        if (! $accountInfo) {
            throw new RuntimeException('확정 SK 기관에 담당자(CO/TR/CS) 정보를 반영하지 못했습니다.');
        }

        $accountInfo->update($patch);
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    /**
     * 알림 제목에 노출할 변경 범위. 값이 비어 있으면 기존 값을 유지하므로 변경으로 보지 않는다.
     *
     * @return array<int, string>
     */
    private function changedFields(SkCodeRequest $req): array
    {
        $fields = [];

        foreach ([
            'institution_name' => 'institution',
            'portal_campus_id' => 'portal',
            'account_no' => 'account',
            'co' => 'assignee',
            'tr' => 'assignee',
            'cs' => 'assignee',
        ] as $column => $field) {
            if ($this->nullableTrimmedString($req->{$column}) !== null) {
                $fields[] = $field;
            }
        }

        return array_values(array_unique($fields));
    }
}
