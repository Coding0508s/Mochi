<?php

namespace App\Actions;

use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class DeletePotentialMeetingDetail
{
    /**
     * 잠재기관(CoNewTarget) 범위 안의 미팅/상담 상세 1건을 삭제합니다.
     */
    public function __invoke(CoNewTarget $target, int $detailId): void
    {
        Gate::authorize('managePotentialInstitutions');

        if ($target->IsContract) {
            throw new AuthorizationException('계약 완료된 잠재기관의 미팅/컨설팅 이력은 삭제할 수 없습니다.');
        }

        $detail = CoNewTargetDetail::query()
            ->whereKey($detailId)
            ->ofAccount((string) ($target->AccountName ?? ''))
            ->when(filled($target->AccountManager), function (Builder $query) use ($target): void {
                $query->where('AccountManager', $target->AccountManager);
            })
            ->first();

        if ($detail === null) {
            throw (new ModelNotFoundException)->setModel(CoNewTargetDetail::class, [$detailId]);
        }

        $detail->delete();
    }
}
