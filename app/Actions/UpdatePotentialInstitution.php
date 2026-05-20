<?php

namespace App\Actions;

use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\SupportRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class UpdatePotentialInstitution
{
    /**
     * 미계약 잠재기관(CoNewTarget) 마스터 정보를 갱신합니다.
     *
     * @param  array{
     *     account_manager?: string|null,
     *     type: string,
     *     gubun: string,
     *     account_name: string,
     *     director?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     connected?: string|null,
     *     possibility?: string|null,
     *     ls: int,
     *     gs_k: int,
     *     gs_e: int
     * }  $payload
     */
    public function __invoke(CoNewTarget $target, array $payload): CoNewTarget
    {
        Gate::authorize('managePotentialInstitutions');

        $user = auth()->user();
        if ($user === null || ! $target->isManagedBy($user)) {
            throw new AuthorizationException('본인이 등록한 잠재기관만 관리할 수 있습니다.');
        }

        if ($target->IsContract) {
            throw new AuthorizationException('계약 완료된 잠재기관은 이 화면에서 수정할 수 없습니다.');
        }

        $oldAccountName = trim((string) ($target->AccountName ?? ''));
        $oldAccountManager = trim((string) ($target->AccountManager ?? ''));

        $ls = max(0, (int) ($payload['ls'] ?? 0));
        $gsK = max(0, (int) ($payload['gs_k'] ?? 0));
        $gsE = max(0, (int) ($payload['gs_e'] ?? 0));

        $newAccountName = trim((string) $payload['account_name']);
        $newAccountManager = $this->nullableTrim($payload['account_manager'] ?? null);

        DB::transaction(function () use (
            $target,
            $payload,
            $ls,
            $gsK,
            $gsE,
            $newAccountName,
            $newAccountManager,
            $oldAccountName,
            $oldAccountManager,
        ): void {
            $target->update([
                'AccountManager' => $newAccountManager,
                'Type' => trim((string) $payload['type']),
                'Gubun' => trim((string) $payload['gubun']),
                'AccountName' => $newAccountName,
                'Director' => $this->nullableTrim($payload['director'] ?? null),
                'Phone' => $this->nullableTrim($payload['phone'] ?? null),
                'Address' => $this->nullableTrim($payload['address'] ?? null),
                'Connected' => $this->nullableTrim($payload['connected'] ?? null),
                'Possibility' => $this->nullableTrim($payload['possibility'] ?? null),
                'LS' => $ls,
                'GS_K' => $gsK,
                'GS_E' => $gsE,
                'Total' => $ls + $gsK + $gsE,
            ]);

            if ($oldAccountName !== '' && $oldAccountName !== $newAccountName) {
                $detailQuery = CoNewTargetDetail::query()->where('AccountName', $oldAccountName);
                if ($oldAccountManager !== '') {
                    $detailQuery->where('AccountManager', $oldAccountManager);
                }
                $detailQuery->update(['AccountName' => $newAccountName]);

                if (Schema::hasColumn('S_SupportInfo_Account', 'potential_target_id')) {
                    SupportRecord::query()
                        ->where('potential_target_id', (int) $target->ID)
                        ->update(['Account_Name' => $newAccountName]);
                }
            }
        });

        return $target->fresh() ?? $target;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
