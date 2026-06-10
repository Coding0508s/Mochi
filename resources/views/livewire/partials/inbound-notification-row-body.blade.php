<div class="flex flex-wrap items-start justify-between gap-x-2 gap-y-1">
    <p class="min-w-0 flex-1 text-sm font-semibold leading-snug text-gray-900">
        {{ $row['headline'] ?? 'E-Ordering 알림' }}
    </p>
    <span class="shrink-0 text-xs tabular-nums text-gray-500">{{ $row['received_at'] ?? '—' }}</span>
</div>

<div class="mt-1.5 flex flex-wrap gap-1.5">
    @if(($row['type'] ?? '') === 'urgent')
        <span class="inline-flex rounded bg-orange-100 px-1.5 py-0.5 text-[11px] font-medium text-orange-800">긴급</span>
    @elseif(($row['status'] ?? '') === 'failed')
        <span class="inline-flex rounded bg-rose-100 px-1.5 py-0.5 text-[11px] font-medium text-rose-800">반영 실패</span>
    @elseif(($row['status'] ?? '') === 'applied')
        <span class="inline-flex rounded bg-emerald-100 px-1.5 py-0.5 text-[11px] font-medium text-emerald-800">반영 완료</span>
    @elseif(($row['status'] ?? '') === 'received')
        <span class="inline-flex rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-900">수신·처리 중</span>
    @else
        <span class="inline-flex rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-700">{{ $row['status'] ?? '—' }}</span>
    @endif
</div>

@if(($row['type'] ?? '') === 'urgent')
    @if(!empty($row['message']))
        <p class="mt-2 rounded bg-orange-50 px-2 py-1.5 text-xs leading-snug text-orange-900">
            {{ $row['message'] }}
        </p>
    @endif
@else
    <dl class="mt-2 space-y-1 text-xs text-gray-700">
        <div class="flex gap-1">
            <dt class="shrink-0 font-medium text-gray-500">URL 기준 SK</dt>
            <dd class="min-w-0 break-all font-mono text-gray-900">{{ $row['sk_code'] }}</dd>
        </div>
        @if(!empty($row['replaces_sk']))
            <div class="flex gap-1">
                <dt class="shrink-0 font-medium text-gray-500">SK CODE 부여</dt>
                <dd class="min-w-0 break-all">
                    <span class="font-mono text-gray-900">{{ $row['replaces_sk'] }}</span>
                    <span class="text-gray-400"> → </span>
                    <span class="font-mono text-gray-900">{{ $row['sk_code'] }}</span>
                </dd>
            </div>
        @endif
        @if(!empty($row['institution_name']))
            <div class="flex gap-1">
                <dt class="shrink-0 font-medium text-gray-500">기관명(요청)</dt>
                <dd class="min-w-0 break-words text-gray-900">{{ $row['institution_name'] }}</dd>
            </div>
        @endif
    </dl>
@endif

@if(($row['type'] ?? '') !== 'urgent' && !empty($row['assignment_changes']))
    <dl class="mt-1 space-y-0.5 text-xs leading-snug text-gray-600">
        @foreach($row['assignment_changes'] as $change)
            <div class="flex gap-1">
                <dt class="shrink-0 font-medium text-gray-500">{{ $change['label'] }}</dt>
                <dd class="min-w-0 break-words">
                    <span>{{ $change['before'] ?? '-' }}</span>
                    <span class="text-gray-400"> → </span>
                    <strong class="font-semibold text-gray-900">{{ $change['after'] }}</strong>
                </dd>
            </div>
        @endforeach
    </dl>
@endif

@if(($row['type'] ?? '') !== 'urgent' && !empty($row['portal_changes']))
    <dl class="mt-1 space-y-0.5 text-xs leading-snug text-gray-600">
        @foreach($row['portal_changes'] as $change)
            <div class="flex gap-1">
                <dt class="shrink-0 font-medium text-gray-500">{{ $change['label'] }}</dt>
                <dd class="min-w-0 break-words">
                    <span>{{ $change['before'] ?? '-' }}</span>
                    <span class="text-gray-400"> → </span>
                    <strong class="font-semibold text-gray-900">{{ $change['after'] }}</strong>
                </dd>
            </div>
        @endforeach
    </dl>
@endif

@if(($row['type'] ?? '') !== 'urgent' && !empty($row['error_message']))
    <p class="mt-2 rounded bg-rose-50 px-2 py-1.5 text-xs leading-snug text-rose-900">
        <span class="font-medium">실패 사유</span>
        {{ $row['error_message'] }}
    </p>
@endif
