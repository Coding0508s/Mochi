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
                'id' => (int) $row->id,
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

    public function deleteLog(int $id): void
    {
        if (! auth()->user()?->hasFullAccess()) {
            return;
        }

        ExternalAssignmentInboundLog::query()->whereKey($id)->delete();

        $this->loadCounters();
    }

    public function deleteAllLogs(): void
    {
        if (! auth()->user()?->hasFullAccess()) {
            return;
        }

        ExternalAssignmentInboundLog::query()->delete();

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
