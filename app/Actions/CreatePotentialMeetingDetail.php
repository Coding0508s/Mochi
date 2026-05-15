<?php

namespace App\Actions;

use App\Mail\PotentialMeetingStoredMail;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreatePotentialMeetingDetail
{
    /**
     * 잠재기관(CoNewTarget)에 연결된 미팅/상담 상세 1건을 생성합니다.
     *
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
    public function __invoke(CoNewTarget $target, array $payload): CoNewTargetDetail
    {
        Gate::authorize('managePotentialInstitutions');

        $user = auth()->user();
        if ($user === null || ! $target->isManagedBy($user)) {
            throw new AuthorizationException('본인이 등록한 잠재기관만 관리할 수 있습니다.');
        }

        if ($target->IsContract) {
            throw new AuthorizationException('계약 완료된 잠재기관에는 미팅을 추가할 수 없습니다.');
        }

        $meetingDate = Carbon::parse($payload['meeting_date']);
        $managerRaw = $payload['account_manager'] ?? null;
        $accountManager = filled($managerRaw)
            ? trim((string) $managerRaw)
            : (filled($target->AccountManager) ? trim((string) $target->AccountManager) : '');
        $accountManager = $accountManager !== '' ? $accountManager : null;

        $meetingDetail = CoNewTargetDetail::query()->create([
            'Year' => (int) $meetingDate->format('Y'),
            'AccountName' => (string) $target->AccountName,
            'AccountManager' => $accountManager,
            'MeetingDate' => $meetingDate->format('Y-m-d'),
            'MeetingTime' => filled($payload['meeting_time'] ?? null) ? trim((string) $payload['meeting_time']) : null,
            'MeetingTime_End' => filled($payload['meeting_time_end'] ?? null) ? trim((string) $payload['meeting_time_end']) : null,
            'Description' => filled($payload['description'] ?? null) ? trim((string) $payload['description']) : null,
            'ConsultingType' => trim((string) $payload['consulting_type']),
            'Possibility' => filled($payload['possibility'] ?? null) ? trim((string) $payload['possibility']) : null,
        ]);

        $this->sendStoredMailAfterCommit($meetingDetail, $target);

        return $meetingDetail;
    }

    private function sendStoredMailAfterCommit(CoNewTargetDetail $meetingDetail, CoNewTarget $target): void
    {
        $notify = config('support_report_mail.notify_addresses', []);
        if ($notify === []) {
            return;
        }

        $submittedBy = auth()->user();

        DB::afterCommit(function () use ($meetingDetail, $notify, $submittedBy, $target): void {
            try {
                Mail::to($notify)->send(new PotentialMeetingStoredMail($meetingDetail, $submittedBy, $target));
            } catch (\Throwable $mailException) {
                report($mailException);
                Log::warning('잠재기관 미팅 내역 알림 메일 발송 실패', [
                    'exception' => $mailException->getMessage(),
                    'notify' => $notify,
                    'meeting_detail_id' => $meetingDetail->getKey(),
                ]);
            }
        });
    }
}
