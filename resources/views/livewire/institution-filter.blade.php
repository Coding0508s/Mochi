<div class="mochi-filter-card">
    @php
        $statusLabelMap = [
            'active' => '운영 기관',
            'terminated' => '해지 기관',
            'all' => '전체',
        ];
        $assignmentLabelMap = [
            'assigned' => '담당자 배정',
            'my_assigned' => '내 담당 기관',
            'unassigned' => '미배정',
        ];

        $activeFilterChips = [];
        if ($statusFilter !== 'all') {
            $activeFilterChips[] = [
                'label' => '상태: '.($statusLabelMap[$statusFilter] ?? $statusFilter),
                'action' => 'clearStatusFilter',
            ];
        }
        if ($assignmentFilter !== '') {
            $activeFilterChips[] = [
                'label' => '배정: '.($assignmentLabelMap[$assignmentFilter] ?? $assignmentFilter),
                'action' => 'clearAssignmentFilter',
            ];
        }
        if ($filterCo !== '') {
            $activeFilterChips[] = ['label' => 'CO: '.$filterCo, 'action' => 'clearCoFilter'];
        }
        if ($filterTr !== '') {
            $activeFilterChips[] = ['label' => 'Coach: '.$filterTr, 'action' => 'clearTrFilter'];
        }
        if ($filterCs !== '') {
            $activeFilterChips[] = ['label' => 'CS: '.$filterCs, 'action' => 'clearCsFilter'];
        }
        if ($search !== '') {
            $activeFilterChips[] = ['label' => '검색어: '.$search, 'action' => 'clearSearchFilter'];
        }
    @endphp

    <div class="flex flex-wrap items-center gap-3 md:flex-nowrap">
        <select wire:model.live="statusFilter"
                class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
            <option value="active">운영 기관</option>
            <option value="terminated">해지 기관</option>
            <option value="all">전체</option>
        </select>

        <div class="relative min-w-0 flex-1 max-md:min-w-[18rem]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="기관명, SK코드, 원장명, 주소 검색"
                   class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
        </div>

        <button type="button"
                wire:click="$parent.exportInstitutionsExcel"
                wire:loading.attr="disabled"
                wire:target="exportInstitutionsExcel"
                class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60">
            <span wire:loading.remove wire:target="exportInstitutionsExcel" class="inline-flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                엑셀 다운로드
            </span>
            <span wire:loading.inline-flex wire:target="exportInstitutionsExcel" class="hidden items-center gap-2">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                생성 중…
            </span>
        </button>

        @if($activeFilterChips !== [])
            <button type="button"
                    wire:click="clearListFilters"
                    class="shrink-0 cursor-pointer rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-1">
                초기화
            </button>
        @endif
    </div>

    @if(! $canToggleViewAllInstitutions && $canViewAllInstitutions)
        <p class="mt-2 text-xs text-gray-500">
            현재 계정은 조회 범위 토글을 사용할 수 없습니다.
        </p>
    @endif

    <div class="mt-3 flex flex-nowrap items-center gap-2 border-t border-gray-100 pt-3 overflow-x-auto">
        <span class="shrink-0 text-xs font-medium text-gray-500">담당자</span>
        <select wire:model.live="filterCo"
                class="min-w-[180px] flex-1 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header max-md:px-2 max-md:text-xs">
            <option value="">CO 전체</option>
            @foreach($coManagerOptions as $managerName)
                <option value="{{ $managerName }}">{{ $managerName }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterTr"
                class="min-w-[180px] flex-1 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header max-md:px-2 max-md:text-xs">
            <option value="">Coach 전체</option>
            @foreach($trManagerOptions as $managerName)
                <option value="{{ $managerName }}">{{ $managerName }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterCs"
                class="min-w-[180px] flex-1 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header max-md:px-2 max-md:text-xs">
            <option value="">CS 전체</option>
            @foreach($csManagerOptions as $managerName)
                <option value="{{ $managerName }}">{{ $managerName }}</option>
            @endforeach
        </select>

        <button type="button"
                wire:click="toggleViewAllInstitutions"
                @disabled(! $canToggleViewAllInstitutions)
                class="inline-flex min-w-[170px] shrink-0 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 transition max-md:px-2 max-md:text-xs disabled:cursor-not-allowed disabled:opacity-60">
            <span class="relative h-6 w-11 rounded-full transition-colors {{ $canViewAllInstitutions ? 'bg-mochi-header' : 'bg-gray-200' }}">
                <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-transform {{ $canViewAllInstitutions ? 'translate-x-5' : 'translate-x-0' }}"></span>
            </span>
            <span class="whitespace-nowrap text-[13px] font-medium">{{ $canViewAllInstitutions ? '모든 기관 조회' : '담당 기관 조회' }}</span>
        </button>
    </div>

    @if($activeFilterChips !== [])
        <div class="mt-3 border-t border-gray-100 pt-3 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-gray-400">적용 필터</span>
                @foreach($activeFilterChips as $chip)
                    <span class="inline-flex items-center gap-1 rounded-full border border-mochi-header/20 bg-mochi-header/10 px-2.5 py-1 font-medium text-mochi-header">
                        {{ $chip['label'] }}
                        <button type="button"
                                wire:click="{{ $chip['action'] }}"
                                class="ml-0.5 text-mochi-header/60 hover:text-mochi-header"
                                aria-label="{{ $chip['label'] }} 필터 해제">
                            ×
                        </button>
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
