<div class="mochi-page">
    @if(session('error'))
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <h2 class="text-base font-semibold text-mochi-header">기관 지원 현황</h2>
            <span class="text-gray-300">|</span>
            <div class="mochi-toggle-group">
                <button type="button"
                        wire:click="setCoverageFilter('')"
                        aria-pressed="{{ $coverageFilter === '' ? 'true' : 'false' }}"
                        class="mochi-toggle-btn {{ $coverageFilter === '' ? 'mochi-toggle-btn--active' : '' }}">
                    전체
                    <span class="font-semibold">{{ $counts['total'] ?? 0 }}</span>
                </button>
                @foreach($coverageFilterLabels as $key => $label)
                    <button type="button"
                            wire:click="setCoverageFilter('{{ $key }}')"
                            aria-pressed="{{ $coverageFilter === $key ? 'true' : 'false' }}"
                            class="mochi-toggle-btn
                                {{ $coverageFilter === $key
                                    ? (str_contains($key, 'unsupported')
                                        ? 'mochi-toggle-btn--active-danger'
                                        : 'mochi-toggle-btn--active-success')
                                    : '' }}">
                        {{ $label }}
                        <span class="font-semibold">{{ $counts[$key] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <select wire:model.live="filterYear"
                        class="py-1.5 px-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    <option value="">전체 (최근 4년)</option>
                    @foreach($yearFilterOptions as $y)
                        <option value="{{ $y }}">{{ $y }}년</option>
                    @endforeach
                </select>
                <button type="button"
                        wire:click="exportToExcel"
                        wire:loading.attr="disabled"
                        wire:target="exportToExcel"
                        class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="exportToExcel" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        엑셀 다운로드
                    </span>
                    <span wire:loading.inline-flex wire:target="exportToExcel" class="hidden items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        생성 중…
                    </span>
                </button>
                <a href="{{ \App\Support\TeamMenuContext::route('coach.teacher-support.index') }}"
                   class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600">
                    교사 지원 현황
                </a>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">
            지원방법 및 횟수 ({{ $yearLabel }}) · 표에는 Coach Team 작성의 대면·전화·화상(완료) 건수만 표시합니다.
            연도 선택 시 해당 연 1월부터 다음 연 3월까지 집계합니다.
        </p>
    </div>

    <div class="mochi-filter-card">
        @php
            $activeFilterChips = [];
            if ($coverageFilter !== '') {
                $activeFilterChips[] = [
                    'label' => '상태: '.($coverageFilterLabels[$coverageFilter] ?? $coverageFilter),
                    'action' => 'clearCoverageFilter',
                ];
            }
            if ($filterCoach !== '') {
                $activeFilterChips[] = ['label' => '담당 코치: '.$filterCoach, 'action' => 'clearCoachFilter'];
            }
            if ($search !== '') {
                $activeFilterChips[] = ['label' => '검색어 적용', 'action' => 'clearSearch'];
            }
        @endphp

        <div class="flex flex-wrap items-center gap-3">
            @if($coachFilterOptions->isNotEmpty())
                <select wire:model.live="filterCoach"
                        class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    <option value="">전체 담당</option>
                    @foreach($coachFilterOptions as $coachName)
                        <option value="{{ $coachName }}">{{ $coachName }}</option>
                    @endforeach
                </select>
            @endif

            @if($search !== '' || $filterCoach !== '' || $coverageFilter !== '')
                <button type="button"
                        wire:click="resetFilters"
                        class="py-2 px-3 text-sm text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                    초기화
                </button>
            @endif

            <div class="relative flex-1 min-w-56">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="기관명, SK코드, 담당 Coach 검색"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"
                       aria-label="기관 검색"/>
            </div>

            <span class="shrink-0 whitespace-nowrap text-sm text-gray-500">
                현재 조건
                <span class="font-semibold text-gray-700">{{ $institutions->total() }}</span>건
            </span>
        </div>

        @if($activeFilterChips !== [])
            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
                @foreach($activeFilterChips as $chip)
                    <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-700">
                        {{ $chip['label'] }}
                        @if($chip['action'])
                            <button type="button"
                                    wire:click="{{ $chip['action'] }}"
                                    class="text-gray-400 hover:text-gray-700"
                                    aria-label="{{ $chip['label'] }} 해제">
                                ×
                            </button>
                        @endif
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mochi-table-card">
        <div class="overflow-x-auto isolate">
            <table class="institution-list-table w-full text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                <tr class="text-gray-700">
                    <th class="institution-sticky-no institution-sticky-no--head px-3 py-2 text-left text-xs font-semibold">No</th>
                    <th class="institution-sticky-sk institution-sticky-sk--head px-3 py-2 text-left text-xs font-semibold">SK코드</th>
                    <th class="institution-sticky-name institution-sticky-name--head w-[16rem] max-w-[16rem] px-3 py-2 text-left text-xs font-semibold">기관명</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">Coach</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">대면</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">전화</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">화상</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">합계</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($institutions as $index => $row)
                    <tr class="mochi-table-row-hover transition-colors" wire:key="coverage-{{ $row['sk_code'] }}">
                        <td class="institution-sticky-no px-3 py-2 text-gray-500 text-xs">{{ $institutions->firstItem() + $index }}</td>
                        <td class="institution-sticky-sk px-3 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                {{ $row['sk_code'] }}
                            </span>
                        </td>
                        <td class="institution-sticky-name w-[16rem] max-w-[16rem] overflow-hidden text-ellipsis px-3 py-2 font-medium text-gray-900"
                            title="{{ $row['institution'] }}">
                            {{ $row['institution'] }}
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $row['coach'] !== '' ? $row['coach'] : '-' }}</td>
                        <td class="px-3 py-2 tabular-nums text-gray-600">
                            @if((int) $row['visit_count'] > 0)
                                <button type="button"
                                        wire:click="openTypeDetail('{{ $row['sk_code'] }}', 'visit')"
                                        class="cursor-pointer font-medium text-mochi-header underline hover:text-blue-800">
                                    {{ \App\Support\CoachTeamInstitutionCoverageAggregator::formatCount((int) $row['visit_count']) }}
                                </button>
                            @else
                                0
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums text-gray-600">
                            @if((int) $row['phone_count'] > 0)
                                <button type="button"
                                        wire:click="openTypeDetail('{{ $row['sk_code'] }}', 'phone')"
                                        class="cursor-pointer font-medium text-mochi-header underline hover:text-blue-800">
                                    {{ \App\Support\CoachTeamInstitutionCoverageAggregator::formatCount((int) $row['phone_count']) }}
                                </button>
                            @else
                                0
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums text-gray-600">
                            @if((int) $row['video_count'] > 0)
                                <button type="button"
                                        wire:click="openTypeDetail('{{ $row['sk_code'] }}', 'video')"
                                        class="cursor-pointer font-medium text-mochi-header underline hover:text-blue-800">
                                    {{ \App\Support\CoachTeamInstitutionCoverageAggregator::formatCount((int) $row['video_count']) }}
                                </button>
                            @else
                                0
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums text-gray-600">
                            {{ \App\Support\CoachTeamInstitutionCoverageAggregator::formatCount((int) $row['institution_total_count']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400">
                            <p class="font-medium">조건에 맞는 기관이 없습니다</p>
                            <p class="text-sm mt-1">다른 조건으로 다시 검색해 보세요.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($institutions->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $institutions->links() }}
            </div>
        @endif
    </div>

    @if($showTypeDetailModal)
        <div class="mochi-modal-overlay" wire:click.self="closeTypeDetail">
            <div class="mochi-modal-shell max-w-3xl max-h-[min(90vh,calc(100dvh-2rem))] min-h-0 flex flex-col" @click.stop>
                <x-admin.modal-header
                    :title="$typeDetailInstitution.' · '.$typeDetailTypeLabel.' 지원 내역'"
                    :subtitle="$typeDetailSkCode.' · '.$yearLabel.' · '.count($typeDetailRows).'건'"
                    close-action="closeTypeDetail"
                />
                <div class="mochi-modal-body-scroll px-6 py-4">
                    <div class="overflow-x-auto border border-gray-200 rounded">
                        <table class="w-full text-sm whitespace-nowrap">
                            <thead class="mochi-table-head text-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold">지원일</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold">담당</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold">유형</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold">상태</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold">ID</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($typeDetailRows as $detail)
                                <tr @class([
                                        'mochi-table-row-hover',
                                        'cursor-pointer hover:bg-blue-50' => filled($detail['detail_key'] ?? null),
                                    ])
                                    wire:key="type-detail-{{ $detail['detail_key'] ?: ($detail['date'].'-'.$loop->index) }}"
                                    @if(filled($detail['detail_key'] ?? null))
                                        wire:click="openTypeDetailRecord('{{ $detail['detail_key'] }}')"
                                        role="button"
                                        tabindex="0"
                                    @endif>
                                    <td class="px-3 py-2 tabular-nums @if(filled($detail['detail_key'] ?? null)) text-mochi-header underline @else text-gray-800 @endif">
                                        {{ $detail['date'] !== '' ? $detail['date'] : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $detail['coach'] !== '' ? $detail['coach'] : '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $detail['type'] !== '' ? $detail['type'] : $typeDetailTypeLabel }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $detail['status'] !== '' ? $detail['status'] : '—' }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $detail['id'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-coach.teacher-support-history-detail-modal
        :show="$showTeacherSupportHistoryDetailModal"
        :detail="$selectedTeacherSupportHistoryDetail"
    />
</div>
