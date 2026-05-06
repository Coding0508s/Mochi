<?php

namespace App\Livewire;

use App\Models\ExternalAssignmentInboundLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

class InboundNotificationBell extends Component
{
    public int $unreadCount = 0;

    public int $recent24hCount = 0;

    /**
     * @var array<int, array{
     *     id:int,
     *     sk_code:string,
     *     status:string,
     *     co:?string,
     *     tr:?string,
     *     cs:?string,
     *     received_at:?string,
     *     is_unread:bool,
     *     headline:string,
     *     institution_name:?string,
     *     replaces_sk:?string,
     *     error_message:?string,
     *     assignment_summary:string
     * }>
     */
    public array $recentRows = [];

    public function mount(): void
    {
        if (auth()->check()) {
            $this->loadCounters();
        }
    }

    public function loadCounters(): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        $this->recent24hCount = ExternalAssignmentInboundLog::query()
            ->where('received_at', '>=', now()->subDay())
            ->count();

        $lastSeen = $user->last_inbound_seen_at;

        if ($user->hasFullAccess()) {
            $unreadQuery = ExternalAssignmentInboundLog::query();
            if ($lastSeen !== null) {
                $unreadQuery->where('received_at', '>', $lastSeen);
            }
            $this->unreadCount = $unreadQuery->count();
        } else {
            $this->unreadCount = 0;
        }

        $rows = ExternalAssignmentInboundLog::query()
            ->orderByDesc('received_at')
            ->limit(10)
            ->get();

        $this->recentRows = $rows->map(function (ExternalAssignmentInboundLog $row) use ($user, $lastSeen): array {
            $isUnread = $user->hasFullAccess()
                && ($lastSeen === null || ($row->received_at !== null && $row->received_at->greaterThan($lastSeen)));

            $raw = is_array($row->raw_body) ? $row->raw_body : [];
            $institutionName = isset($raw['institution_name']) && is_string($raw['institution_name'])
                ? trim($raw['institution_name'])
                : null;
            $institutionName = $institutionName !== '' ? $institutionName : null;

            $replacesSk = isset($raw['replaces_sk']) && is_string($raw['replaces_sk'])
                ? trim($raw['replaces_sk'])
                : null;
            $replacesSk = $replacesSk !== '' ? $replacesSk : null;

            $skCode = (string) $row->sk_code;
            $status = (string) $row->status;

            $headline = match ($status) {
                'failed' => 'E-Ordering에서 보낸 기관 연동 요청이 실패했습니다.',
                'received' => 'E-Ordering에서  요청을 받았습니다. 처리 중이거나 중단된 건일 수 있습니다.',
                default => $replacesSk !== null
                    ? (($institutionName ?? $skCode).' 기관의 SK 코드가 등록되었습니다.')
                    : 'E-Ordering에서 보낸 기관 정보가 등록되었습니다.',
            };

            $parts = [];
            if ($row->co !== null && trim((string) $row->co) !== '') {
                $parts[] = 'CO '.trim((string) $row->co);
            }
            if ($row->tr !== null && trim((string) $row->tr) !== '') {
                $parts[] = 'TR '.trim((string) $row->tr);
            }
            if ($row->cs !== null && trim((string) $row->cs) !== '') {
                $parts[] = 'CS '.trim((string) $row->cs);
            }
            $assignmentSummary = $parts !== []
                ? '기관에 배정된 담당자: '.implode(' · ', $parts)
                : 'E-Ordering 요청 본문에는 CO/TR/CS 담당자 변경이 포함되지 않았습니다.';

            $err = $row->error_message;
            $errorMessage = is_string($err) && trim($err) !== ''
                ? Str::limit(trim($err), 200)
                : null;

            return [
                'id' => (int) $row->id,
                'sk_code' => $skCode,
                'status' => $status,
                'co' => $row->co,
                'tr' => $row->tr,
                'cs' => $row->cs,
                'received_at' => $row->received_at?->format('Y-m-d H:i'),
                'is_unread' => $isUnread,
                'headline' => $headline,
                'institution_name' => $institutionName,
                'replaces_sk' => $replacesSk,
                'error_message' => $errorMessage,
                'assignment_summary' => $assignmentSummary,
            ];
        })->all();
    }

    public function markAllAsRead(): void
    {
        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            return;
        }

        $user->last_inbound_seen_at = now();
        $user->save();

        $this->loadCounters();
    }

    public function render(): View
    {
        return view('livewire.inbound-notification-bell');
    }
}
