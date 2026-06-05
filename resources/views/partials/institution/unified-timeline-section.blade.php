@php
    $timelineItems = $timelineVisibleItems ?? [];
    $timelineTotals = $timelineTypeTotals ?? [
        'all' => 0,
        'support' => 0,
        'support_coach' => 0,
        'support_cs' => 0,
        'assignment_change' => 0,
        'contract_document' => 0,
    ];
    $latestSupportDate = $selectedInstitution['latest_support_date'] ?? null;
    $latestSupportDateLabel = $latestSupportDate ? substr((string) $latestSupportDate, 0, 10) : '-';
@endphp

<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="timelineTypeFilter"
                    class="py-2 px-3 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="all">전체 유형</option>
                <option value="support">지원 내역</option>
                <option value="support_coach">코치 교사 지원</option>
                <option value="support_cs">CS 기관 지원</option>
                <option value="assignment_change">담당자 변경</option>
                <option value="contract_document">계약 문서</option>
            </select>

            <select wire:model.live="timelineRangeFilter"
                    class="py-2 px-3 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="1m">최근 1개월</option>
                <option value="3m">최근 3개월</option>
                <option value="6m">최근 6개월</option>
                <option value="12m">최근 12개월</option>
                <option value="all">전체</option>
            </select>

            <input type="text"
                   wire:model.live.debounce.400ms="timelineAuthorFilter"
                   placeholder="작성자 검색"
                   class="min-w-40 py-2 px-3 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />

            <button type="button"
                    wire:click="loadTimeline"
                    class="py-2 px-3 text-xs text-white bg-mochi-header rounded-lg hover:bg-mochi-header/90 transition-colors cursor-pointer">
                새로고침
            </button>

            <div class="ml-auto text-xs text-gray-500">
                총 <span class="font-semibold text-gray-700">{{ $timelineTotals['all'] ?? 0 }}</span>건
            </div>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] text-gray-500">
            <span class="inline-flex items-center rounded-full border border-blue-200/80 bg-blue-50/70 px-2.5 py-0.5 font-medium text-blue-800 shadow-[inset_0_1px_0_rgba(255,255,255,0.6)]">지원 {{ $timelineTotals['support'] ?? 0 }}</span>
            <span class="inline-flex items-center rounded-full border border-violet-200/80 bg-violet-50/70 px-2.5 py-0.5 font-medium text-violet-800 shadow-[inset_0_1px_0_rgba(255,255,255,0.6)]">코치 교사 {{ $timelineTotals['support_coach'] ?? 0 }}</span>
            <span class="inline-flex items-center rounded-full border border-teal-200/80 bg-teal-50/70 px-2.5 py-0.5 font-medium text-teal-800 shadow-[inset_0_1px_0_rgba(255,255,255,0.6)]">CS 기관 {{ $timelineTotals['support_cs'] ?? 0 }}</span>
            <span class="inline-flex items-center rounded-full border border-purple-200/80 bg-purple-50/70 px-2.5 py-0.5 font-medium text-purple-800 shadow-[inset_0_1px_0_rgba(255,255,255,0.6)]">담당자 변경 {{ $timelineTotals['assignment_change'] ?? 0 }}</span>
            <span class="inline-flex items-center rounded-full border border-sky-200/80 bg-sky-50/70 px-2.5 py-0.5 font-medium text-sky-800 shadow-[inset_0_1px_0_rgba(255,255,255,0.6)]">계약 {{ $timelineTotals['contract_document'] ?? 0 }}</span>
        </div>
        <div class="mt-2 text-[11px] text-gray-400" wire:loading wire:target="timelineTypeFilter,timelineRangeFilter,timelineAuthorFilter,loadTimeline,loadMoreTimeline">
            타임라인 데이터를 불러오는 중...
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <p class="text-[11px] text-gray-500">총 지원 횟수</p>
            <p class="mt-1 text-xl font-bold text-gray-900">{{ $selectedInstitution['support_count'] ?? 0 }}회</p>
            <p class="mt-1 text-[11px] text-blue-700">최근 6개월 기준 필터 적용 가능</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <p class="text-[11px] text-gray-500">등록 교사</p>
            <p class="mt-1 text-xl font-bold text-gray-900">{{ $selectedInstitution['teacher_count'] ?? 0 }}명</p>
            <p class="mt-1 text-[11px] text-green-700">기관 상세 요약 기준</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <p class="text-[11px] text-gray-500">마지막 방문</p>
            <p class="mt-1 text-xl font-bold text-gray-900">{{ $latestSupportDateLabel }}</p>
            <p class="mt-1 text-[11px] text-amber-700">지원 내역 최신일 기준</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <p class="text-[11px] text-gray-500">기관 건강 점수 (MVP 임시식)</p>
            <p class="mt-1 text-xl font-bold text-gray-900">{{ $timelineHealthScore ?? 0 }}점</p>
            <p class="mt-1 text-[11px] text-rose-700">2차에서 정교화 예정</p>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white">
        <div class="max-h-[46vh] overflow-y-auto">
            @forelse($timelineItems as $event)
                @php
                    $type = (string) ($event['event_type'] ?? 'support');
                    $badgeClass = match ($type) {
                        'support_coach' => 'border-violet-200/80 bg-violet-50/70 text-violet-800',
                        'support_cs' => 'border-teal-200/80 bg-teal-50/70 text-teal-800',
                        'assignment_change' => 'border-purple-200/80 bg-purple-50/70 text-purple-800',
                        'contract_document' => 'border-sky-200/80 bg-sky-50/70 text-sky-800',
                        default => 'border-blue-200/80 bg-blue-50/70 text-blue-800',
                    };
                @endphp
                <div class="border-b border-gray-100 px-4 py-3 last:border-b-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-[0.01em] shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] {{ $badgeClass }}">
                            {{ $event['event_type_label'] ?? '-' }}
                        </span>
                        <span class="text-xs text-gray-500">
                            {{ $event['occurred_date'] ?? '-' }} {{ $event['occurred_time'] ?? '' }}
                        </span>
                        @if(! empty($event['status']))
                            <span class="text-[11px] text-gray-500">상태: {{ $event['status'] }}</span>
                        @endif
                    </div>

                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $event['title'] ?? '-' }}</p>
                    <p class="mt-1 text-xs text-gray-600">{{ $event['summary'] ?? '-' }}</p>
                    <p class="mt-1 text-[11px] text-gray-400">작성자: {{ $event['actor_name'] ?? '-' }}</p>

                    <div class="mt-2 flex flex-wrap gap-2">
                        @if(($event['event_type'] ?? null) === 'support' && ! empty($event['source_id']))
                            <button type="button"
                                    wire:click="openSupportDetailModal({{ (int) $event['source_id'] }})"
                                    class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-700 hover:bg-blue-100">
                                지원 상세 보기
                            </button>
                        @endif
                        @if(($event['event_type'] ?? null) === 'support_cs' && ! empty($event['source_id']))
                            <button type="button"
                                    wire:click="openSupportDetailModal({{ (int) $event['source_id'] }})"
                                    class="inline-flex items-center rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[11px] font-medium text-teal-700 hover:bg-teal-100">
                                CS 기관 지원 상세 보기
                            </button>
                        @endif
                        @if(($event['event_type'] ?? null) === 'support_coach' && ! empty($event['detail_key']))
                            <button type="button"
                                    wire:click.stop="openTeacherSupportHistoryDetail('{{ $event['detail_key'] }}', {{ $event['teacher_id'] ?? 'null' }})"
                                    class="inline-flex items-center rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 text-[11px] font-medium text-violet-700 hover:bg-violet-100">
                                코치 교사 지원 상세 보기
                            </button>
                        @endif
                        @if(($event['event_type'] ?? null) === 'contract_document' && ! empty($event['source_id']))
                            <a href="{{ route('contract-documents.preview', ['contractDocument' => (int) $event['source_id']]) }}"
                               target="_blank"
                               class="inline-flex items-center rounded-lg border border-sky-200 bg-sky-50 px-2 py-1 text-[11px] font-medium text-sky-700 hover:bg-sky-100">
                                계약 문서 보기
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-10 text-center text-sm text-gray-400">
                    조건에 맞는 통합 타임라인 기록이 없습니다.
                </div>
            @endforelse
        </div>

        @if($timelineHasMore ?? false)
            <div class="border-t border-gray-100 px-4 py-3 text-center">
                <button type="button"
                        wire:click="loadMoreTimeline"
                        class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        wire:loading.attr="disabled"
                        wire:target="loadMoreTimeline">
                    <span wire:loading.remove wire:target="loadMoreTimeline">더보기</span>
                    <span wire:loading wire:target="loadMoreTimeline">불러오는 중...</span>
                </button>
            </div>
        @endif
    </div>
</div>

