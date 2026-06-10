<?php

namespace App\Livewire;

use App\Models\ExternalAssignmentInboundLog;
use App\Models\InboundNotificationDismissal;
use App\Models\UrgentSupportNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class InboundNotificationBell extends Component
{
    public int $unreadCount = 0;

    /**
     * @var array<int, array{
     *     type:string,
     *     row_key:string,
     *     id:int,
     *     href:string,
     *     sk_code:string,
     *     status:string,
     *     co:?string,
     *     tr:?string,
     *     cs:?string,
     *     portal_campus_id:?string,
     *     account_no:?string,
     *     received_at:?string,
     *     is_unread:bool,
     *     headline:string,
     *     institution_name:?string,
     *     replaces_sk:?string,
     *     error_message:?string,
     *     assignment_changes:array<int, array{label:string, before:?string, after:string}>,
     *     portal_changes:array<int, array{label:string, before:?string, after:string}>
     * }>
     */
    public array $recentRows = [];

    public function mount(): void
    {
        if (auth()->check()) {
            $this->loadCounters();
        }
    }

    #[On('notifications-updated')]
    public function refreshNotifications(): void
    {
        $this->loadCounters();
    }

    public function loadCounters(): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        $lastSeen = $user->last_inbound_seen_at;

        // 사용자별 "내 화면에서 숨김"한 로그 id 목록. 카운트와 목록 양쪽에 같은 필터를 적용해
        // 다른 사용자에는 영향을 주지 않으면서 본인 알림만 비웁니다.
        $dismissedIds = InboundNotificationDismissal::query()
            ->where('user_id', $user->id)
            ->pluck('log_id')
            ->all();

        $unreadQuery = ExternalAssignmentInboundLog::query()
            ->when($dismissedIds !== [], fn ($q) => $q->whereNotIn('id', $dismissedIds));
        if ($lastSeen !== null) {
            $unreadQuery->where('received_at', '>', $lastSeen);
        }
        $inboundUnreadCount = $unreadQuery->count();

        $urgentUnreadCount = UrgentSupportNotification::query()
            ->where('recipient_user_id', $user->id)
            ->whereNull('dismissed_at')
            ->where('is_read', false)
            ->count();

        $this->unreadCount = $inboundUnreadCount + $urgentUnreadCount;

        $rows = ExternalAssignmentInboundLog::query()
            ->when($dismissedIds !== [], fn ($q) => $q->whereNotIn('id', $dismissedIds))
            ->orderByDesc('received_at')
            ->limit(10)
            ->get();

        $inboundRows = $rows->map(function (ExternalAssignmentInboundLog $row) use ($lastSeen): array {
            $isUnread = $lastSeen === null || ($row->received_at !== null && $row->received_at->greaterThan($lastSeen));

            $raw = is_array($row->raw_body) ? $row->raw_body : [];
            $institutionName = $this->resolveInboundInstitutionName($raw);

            $replacesSk = isset($raw['replaces_sk']) && is_string($raw['replaces_sk'])
                ? trim($raw['replaces_sk'])
                : null;
            $replacesSk = $replacesSk !== '' ? $replacesSk : null;

            $skCode = (string) $row->sk_code;
            $status = (string) $row->status;
            $before = isset($raw['before']) && is_array($raw['before']) ? $raw['before'] : [];
            $assignmentChanges = $this->changesFromRaw($raw, $before, [
                'co' => 'CO',
                'tr' => 'Coach',
                'cs' => 'CS',
            ]);

            $portalChanges = $this->changesFromRaw($raw, $before, [
                'portal_campus_id' => 'Campus ID',
                'account_no' => 'Account ID',
            ]);
            $portalCampusId = $this->trimmedRawValue($raw, 'portal_campus_id');
            $accountNo = $this->trimmedRawValue($raw, 'account_no');

            $headline = match ($status) {
                'failed' => 'E-Ordering에서 보낸 기관 연동 요청이 실패했습니다.',
                'received' => 'E-Ordering에서  요청을 받았습니다. 처리 중이거나 중단된 건일 수 있습니다.',
                default => $this->appliedHeadline(
                    $institutionName,
                    $skCode,
                    $replacesSk,
                    $assignmentChanges,
                    $portalChanges
                ),
            };

            $err = $row->error_message;
            $errorMessage = is_string($err) && trim($err) !== ''
                ? Str::limit(trim($err), 200)
                : null;

            return [
                'type' => 'inbound',
                'row_key' => 'inbound:'.$row->id,
                'id' => (int) $row->id,
                'href' => str_starts_with($skCode, 'LEAD-')
                    ? route('potential-institutions.view')
                    : route('institutions.index'),
                'sk_code' => $skCode,
                'status' => $status,
                'co' => $row->co,
                'tr' => $row->tr,
                'cs' => $row->cs,
                'portal_campus_id' => $portalCampusId,
                'account_no' => $accountNo,
                'received_at' => $row->received_at?->format('Y-m-d H:i'),
                'is_unread' => $isUnread,
                'headline' => $headline,
                'institution_name' => $institutionName,
                'replaces_sk' => $replacesSk,
                'error_message' => $errorMessage,
                'assignment_changes' => $assignmentChanges,
                'portal_changes' => $portalChanges,
                'sort_at' => $row->received_at?->timestamp ?? 0,
            ];
        });

        $urgentRows = UrgentSupportNotification::query()
            ->where('recipient_user_id', $user->id)
            ->whereNull('dismissed_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (UrgentSupportNotification $row): array {
                $headlineLabel = filled($row->account_name) ? (string) $row->account_name : (string) ($row->sk_code ?: '기관');
                $summary = trim((string) ($row->message ?? ''));
                $summary = $summary !== '' ? Str::limit($summary, 120) : '"긴급 알림" 확인이 필요한 기관 지원 보고서가 등록되었습니다.';

                return [
                    'type' => 'urgent',
                    'row_key' => 'urgent:'.$row->id,
                    'id' => (int) $row->id,
                    'href' => route('supports.index'),
                    'sk_code' => (string) ($row->sk_code ?? '-'),
                    'status' => 'urgent',
                    'co' => null,
                    'tr' => null,
                    'cs' => null,
                    'portal_campus_id' => null,
                    'account_no' => null,
                    'received_at' => $row->created_at?->format('Y-m-d H:i') ?? '—',
                    'is_unread' => ! $row->is_read,
                    'headline' => "[긴급] {$headlineLabel} 기관 지원 보고서",
                    'institution_name' => $row->account_name,
                    'replaces_sk' => null,
                    'error_message' => null,
                    'assignment_changes' => [],
                    'portal_changes' => [],
                    'message' => $summary,
                    'sort_at' => $row->created_at?->timestamp ?? 0,
                ];
            });

        $this->recentRows = $inboundRows
            ->concat($urgentRows)
            ->sortByDesc('sort_at')
            ->take(10)
            ->values()
            ->map(function (array $row): array {
                unset($row['sort_at']);

                return $row;
            })
            ->all();
    }

    public function markAllAsRead(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $user->last_inbound_seen_at = now();
        $user->save();

        UrgentSupportNotification::query()
            ->where('recipient_user_id', $user->id)
            ->whereNull('dismissed_at')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        $this->loadCounters();
    }

    public function deleteLog(int|string $id): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        if (is_string($id) && str_starts_with($id, 'urgent:')) {
            $urgentId = (int) Str::after($id, 'urgent:');
            if ($urgentId > 0) {
                UrgentSupportNotification::query()
                    ->where('id', $urgentId)
                    ->where('recipient_user_id', $user->id)
                    ->whereNull('dismissed_at')
                    ->update([
                        'is_read' => true,
                        'read_at' => now(),
                        'dismissed_at' => now(),
                    ]);
            }

            $this->loadCounters();

            return;
        }

        if (is_string($id) && str_starts_with($id, 'inbound:')) {
            $id = (int) Str::after($id, 'inbound:');
        }

        $logId = (int) $id;
        if ($logId <= 0) {
            return;
        }

        // 실제 로그는 보존하고, 현재 사용자에게만 안 보이도록 dismiss 흔적만 남깁니다.
        InboundNotificationDismissal::query()->updateOrCreate(
            ['user_id' => $user->id, 'log_id' => $logId],
            ['dismissed_at' => now()],
        );

        $this->loadCounters();
    }

    public function deleteAllLogs(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        // 현재 사용자에게 보이는(=아직 dismiss 안 한) 로그를 일괄 dismiss 처리합니다.
        $alreadyDismissedIds = InboundNotificationDismissal::query()
            ->where('user_id', $user->id)
            ->pluck('log_id')
            ->all();

        $visibleLogIds = ExternalAssignmentInboundLog::query()
            ->when($alreadyDismissedIds !== [], fn ($q) => $q->whereNotIn('id', $alreadyDismissedIds))
            ->pluck('id')
            ->all();

        if ($visibleLogIds !== []) {
            $now = now();
            $rows = array_map(fn (int $logId): array => [
                'user_id' => $user->id,
                'log_id' => $logId,
                'dismissed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_map('intval', $visibleLogIds));

            InboundNotificationDismissal::query()->insert($rows);
        }

        UrgentSupportNotification::query()
            ->where('recipient_user_id', $user->id)
            ->whereNull('dismissed_at')
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'dismissed_at' => now(),
            ]);

        $this->loadCounters();
    }

    public function render(): View
    {
        return view('livewire.inbound-notification-bell');
    }

    /**
     * @param  array<int, array{label:string, before:?string, after:string}>  $assignmentChanges
     * @param  array<int, array{label:string, before:?string, after:string}>  $portalChanges
     */
    private function appliedHeadline(
        ?string $institutionName,
        string $skCode,
        ?string $replacesSk,
        array $assignmentChanges,
        array $portalChanges
    ): string {
        $name = $institutionName ?? $skCode;

        if ($assignmentChanges !== [] && $portalChanges !== []) {
            return "{$name} 기관의 담당자와 포털/사업자 정보가 변경되었습니다.";
        }

        if ($assignmentChanges !== []) {
            return "{$name} 기관의 담당자가 변경되었습니다.";
        }

        if ($portalChanges !== []) {
            return "{$name} 기관의 포털/사업자 정보가 변경되었습니다.";
        }

        if ($replacesSk !== null) {
            return "{$name} 기관의 SK 코드가 등록되었습니다.";
        }

        return "{$name} 기관 정보가 반영되었습니다.";
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function trimmedRawValue(array $raw, string $key): ?string
    {
        if (! isset($raw[$key])) {
            return null;
        }

        $value = trim((string) $raw[$key]);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $before
     * @param  array<string, string>  $labels
     * @return array<int, array{label:string, before:?string, after:string}>
     */
    private function changesFromRaw(array $raw, array $before, array $labels): array
    {
        $changes = [];

        foreach ($labels as $key => $label) {
            if (! array_key_exists($key, $before)) {
                continue;
            }

            $afterNorm = $this->normalizeInboundScalar($raw[$key] ?? null);
            if ($afterNorm === null) {
                continue;
            }

            $beforeNorm = $this->normalizeInboundScalar($before[$key] ?? null);
            if ($beforeNorm === $afterNorm) {
                continue;
            }

            $changes[] = [
                'label' => $label,
                'before' => $beforeNorm,
                'after' => $afterNorm,
            ];
        }

        return $changes;
    }

    /**
     * 알림 본문·기관명(요청) 표시용. 상대 플랫폼이 키 이름을 다르게 보낸 과거 로그도 읽을 수 있게 한다.
     *
     * @param  array<string, mixed>  $raw
     */
    private function resolveInboundInstitutionName(array $raw): ?string
    {
        foreach (['institution_name', 'institutionName', 'account_name', 'accountName'] as $key) {
            if (! isset($raw[$key]) || ! is_string($raw[$key])) {
                continue;
            }
            $trimmed = trim($raw[$key]);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        $patch = $raw['patch'] ?? null;
        if (is_array($patch) && isset($patch['institution_name']) && is_string($patch['institution_name'])) {
            $trimmed = trim($patch['institution_name']);

            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }

    private function normalizeInboundScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $s = trim($value);

            return $s === '' ? null : $s;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
