<?php

namespace App\Actions;

use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class UpdatePotentialMeetingDetail
{
    /**
     * @param  array{
     *     meeting_date: string,
     *     consulting_type: string,
     *     meeting_time?: string|null,
     *     meeting_time_end?: string|null,
     *     description?: string|null,
     *     possibility?: string|null,
     *     account_manager?: string|null
     * }  $payload
     */
    public function __invoke(CoNewTarget $target, int $detailId, array $payload): CoNewTargetDetail
    {
        Gate::authorize('managePotentialInstitutions');

        $user = auth()->user();
        if ($user === null || ! $target->isManagedBy($user)) {
            throw new AuthorizationException('본인이 등록한 잠재기관만 관리할 수 있습니다.');
        }

        if ($target->IsContract) {
            throw new AuthorizationException('계약 완료된 잠재기관의 미팅/컨설팅 이력은 수정할 수 없습니다.');
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

        $meetingDate = Carbon::parse($payload['meeting_date']);
        $accountManager = filled($payload['account_manager'] ?? null)
            ? trim((string) $payload['account_manager'])
            : null;

        $detail->update([
            'Year' => (int) $meetingDate->format('Y'),
            'MeetingDate' => $meetingDate->format('Y-m-d'),
            'MeetingTime' => filled($payload['meeting_time'] ?? null) ? trim((string) $payload['meeting_time']) : null,
            'MeetingTime_End' => filled($payload['meeting_time_end'] ?? null) ? trim((string) $payload['meeting_time_end']) : null,
            'ConsultingType' => trim((string) $payload['consulting_type']),
            'Possibility' => filled($payload['possibility'] ?? null) ? trim((string) $payload['possibility']) : null,
            'Description' => filled($payload['description'] ?? null) ? trim((string) $payload['description']) : null,
            'AccountManager' => $accountManager,
        ]);

        return $detail->fresh() ?? $detail;
    }
}
