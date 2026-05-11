<?php

namespace App\Jobs;

use App\Actions\UpsertInstitutionFromExternal;
use App\Models\External\PartnerInstitution;
use App\Models\ExternalAssignmentInboundLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class PullInstitutionFromPartnerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function handle(UpsertInstitutionFromExternal $upsert): void
    {
        if (! (bool) config('services.partner_institutions.enabled', false)) {
            return;
        }

        $batchSize = max(1, (int) config('services.partner_institutions.batch_size', 100));
        $lastAppliedChangedAt = null;

        PartnerInstitution::query()
            ->pendingForInstitutionSync()
            ->orderBy($this->orderColumn())
            ->limit($batchSize)
            ->get()
            ->each(function (PartnerInstitution $partnerInstitution) use ($upsert, &$lastAppliedChangedAt): void {
                $changedAt = $partnerInstitution->syncChangedAt();
                $this->applyPartnerInstitution($partnerInstitution, $upsert);

                if ($changedAt !== null) {
                    $lastAppliedChangedAt = $changedAt;
                }
            });

        if ($lastAppliedChangedAt !== null) {
            cache()->forever(
                (string) config('services.partner_institutions.state_cache_key', 'partner_institution_sync:last_changed_at'),
                $lastAppliedChangedAt
            );
        }
    }

    private function applyPartnerInstitution(PartnerInstitution $partnerInstitution, UpsertInstitutionFromExternal $upsert): void
    {
        $sk = $partnerInstitution->syncSk();
        $patch = $partnerInstitution->toInstitutionPatch();
        if (! (bool) config('services.partner_institutions.sync_institution_name', true)) {
            unset($patch['institution_name']);
        }
        $replacesSk = $partnerInstitution->syncReplacesSk();

        $log = ExternalAssignmentInboundLog::query()->create([
            'sk_code' => $sk ?: '(missing)',
            'co' => $patch['co'] ?? null,
            'tr' => $patch['tr'] ?? null,
            'cs' => $patch['cs'] ?? null,
            'raw_body' => [
                'source' => 'partner_db',
                'partner_key' => $partnerInstitution->getKey(),
                'patch' => $patch,
                'replaces_sk' => $replacesSk,
            ],
            'status' => 'received',
            'received_at' => now(),
        ]);

        try {
            if ($sk === null) {
                throw new \InvalidArgumentException('상대 기관 행의 SK 코드가 비어 있습니다.');
            }

            if ((bool) config('services.partner_institutions.require_sk_with_portal_and_account', true)) {
                $this->assertPatchIncludesPortalCampusAndAccountNo($patch);
            }

            $upsert->execute($sk, $patch, $replacesSk);
            $partnerInstitution->markInstitutionSyncApplied();

            $log->update([
                'status' => 'applied',
                'applied_at' => now(),
            ]);
        } catch (Throwable $e) {
            $partnerInstitution->markInstitutionSyncFailed();

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::warning('partner_institution_sync_failed', [
                'partner_key' => $partnerInstitution->getKey(),
                'sk' => $sk,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function orderColumn(): string
    {
        $changedAtColumn = config('services.partner_institutions.changed_at_column');
        if (is_string($changedAtColumn) && $changedAtColumn !== '') {
            return $changedAtColumn;
        }

        return (string) config('services.partner_institutions.primary_key', 'id');
    }

    /**
     * SK가 있는 행은 연동 테이블 같은 행에 Portal Campus ID·사업자/기관번호가 비어 있지 않아야 한다.
     *
     * @param  array<string, mixed>  $patch
     */
    private function assertPatchIncludesPortalCampusAndAccountNo(array $patch): void
    {
        $rules = [
            'portal_campus_id' => 'Portal Campus ID',
            'account_no' => '사업자/기관번호',
        ];

        foreach ($rules as $key => $label) {
            if (! array_key_exists($key, $patch)) {
                throw new \InvalidArgumentException(
                    "상대 연동 행에 확정 SK와 함께 {$label}({$key}) 컬럼·값이 필요합니다. 같은 행에 포함해 주세요."
                );
            }

            $value = $patch[$key];
            if (! $this->isNonEmptyPartnerScalar($value)) {
                throw new \InvalidArgumentException(
                    "상대 연동 행에 확정 SK와 함께 {$label}({$key}) 값이 비어 있습니다. 같은 행에서 채운 뒤 동기화해 주세요."
                );
            }
        }
    }

    private function isNonEmptyPartnerScalar(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return false;
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return false;
    }
}
