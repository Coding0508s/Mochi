<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm" data-mochi-flash-dismiss="3000" role="status">
            {{ session('success') }}
        </div>
    @endif

    {{-- Summary --}}
    <div class="mochi-summary-card">
        @php
            $kpiToggleLabels = [
                'first_round' => '1차 대상',
                'second_round' => '2차 대상',
                'completed' => '지원 완료',
                'unsupported' => '미지원',
            ];
        @endphp

        <div class="flex flex-wrap items-center gap-3 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">교사 지원 현황</h2>
            <span class="text-gray-300">|</span>
            <div class="mochi-toggle-group">
                @foreach($kpiToggleLabels as $kpiKey => $kpiLabel)
                    <button type="button"
                            wire:click="setKpiFilter('{{ $kpiKey }}')"
                            aria-pressed="{{ $kpiFilter === $kpiKey ? 'true' : 'false' }}"
                            class="mochi-toggle-btn
                                {{ $kpiFilter === $kpiKey
                                    ? ($kpiKey === 'completed'
                                        ? 'mochi-toggle-btn--active-success'
                                        : ($kpiKey === 'unsupported'
                                            ? 'mochi-toggle-btn--active-danger'
                                            : 'mochi-toggle-btn--active'))
                                    : '' }}">
                        {{ $kpiLabel }}
                        <span class="font-semibold">{{ $kpis[$kpiKey] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <select wire:model.live="filterYear"
                        class="py-1.5 px-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}">{{ $y }}년</option>
                    @endfor
                </select>
                <a href="{{ \App\Support\TeamMenuContext::route('supports.index') }}"
                   class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600">
                    기관지원보고서
                </a>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mochi-filter-card">
        @php
            $activeFilterChips = [];

            if ($kpiFilter) {
                $activeFilterChips[] = ['label' => '상태: '.($kpiToggleLabels[$kpiFilter] ?? $kpiFilter), 'action' => 'clearKpiFilter'];
            }

            if ($filterRound) {
                $activeFilterChips[] = ['label' => '차수: '.$filterRound.'차', 'action' => 'clearRoundFilter'];
            }

            if ($filterMonth) {
                $activeFilterChips[] = ['label' => '1차 계획월: '.$filterYear.'년 '.$filterMonth.'월', 'action' => 'clearMonthFilter'];
            }

            if ($search) {
                $activeFilterChips[] = ['label' => '검색어 적용', 'action' => 'clearSearch'];
            }

            if ($showAllTeachers) {
                $activeFilterChips[] = ['label' => '퇴직 포함', 'action' => null];
            }
        @endphp

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 text-sm">
                <span class="text-gray-500">계획 차수</span>
                <div class="mochi-toggle-group">
                    <button type="button" wire:click="$set('filterRound', '')"
                            class="mochi-toggle-btn {{ $filterRound === '' ? 'mochi-toggle-btn--active' : '' }}">
                        전체
                    </button>
                    <button type="button" wire:click="$set('filterRound', '1')"
                            class="mochi-toggle-btn {{ $filterRound === '1' ? 'mochi-toggle-btn--active' : '' }}">
                        1차
                    </button>
                    <button type="button" wire:click="$set('filterRound', '2')"
                            class="mochi-toggle-btn {{ $filterRound === '2' ? 'mochi-toggle-btn--active' : '' }}">
                        2차
                    </button>
                </div>
            </div>

            <select wire:model.live="filterMonth"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="">전체</option>
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}">{{ $m }}월</option>
                @endfor
            </select>

            @if($search || $filterRound || $filterMonth || $kpiFilter)
                <button wire:click="resetFilters"
                        class="py-2 px-3 text-sm text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                    초기화
                </button>
            @endif

            <div class="relative flex-1 min-w-56">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="이름, 기관명, SK코드 검색"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-gray-500">퇴직</span>
                    <div class="mochi-toggle-group">
                        <button type="button" wire:click="$set('showAllTeachers', false)"
                                class="mochi-toggle-btn {{ ! $showAllTeachers ? 'mochi-toggle-btn--active' : '' }}">
                            제외
                        </button>
                        <button type="button" wire:click="$set('showAllTeachers', true)"
                                class="mochi-toggle-btn {{ $showAllTeachers ? 'mochi-toggle-btn--active' : '' }}">
                            포함
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-sm">
                    <span class="text-gray-500">컬럼</span>
                    <div class="mochi-toggle-group">
                        <button type="button" wire:click="$set('showExtendedColumns', false)"
                                class="mochi-toggle-btn {{ ! $showExtendedColumns ? 'mochi-toggle-btn--active' : '' }}">
                            기본
                        </button>
                        <button type="button" wire:click="$set('showExtendedColumns', true)"
                                class="mochi-toggle-btn {{ $showExtendedColumns ? 'mochi-toggle-btn--active' : '' }}">
                            확장
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if($activeFilterChips !== [])
            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 text-xs">
                <span class="text-gray-400">적용 필터</span>
                @foreach($activeFilterChips as $chip)
                    <span class="inline-flex items-center gap-1 rounded-full border border-mochi-header/20 bg-mochi-header/10 px-2.5 py-1 font-medium text-mochi-header">
                        {{ $chip['label'] }}
                        @if($chip['action'])
                            <button type="button"
                                    wire:click="{{ $chip['action'] }}"
                                    class="ml-0.5 text-mochi-header/60 hover:text-mochi-header"
                                    aria-label="{{ $chip['label'] }} 필터 해제">
                                ×
                            </button>
                        @endif
                    </span>
                @endforeach
            </div>
        @endif

        <p class="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
            계획·완료 칸을 클릭하면 일정을 수정할 수 있습니다.
        </p>
    </div>

    {{-- Table --}}
    @php
        $items = $teachers->items();
        $cols = config('coach_teacher_support.columns');
        $rowspans = [];
        $i = 0;
        while ($i < count($items)) {
            $sk = $items[$i]->SK_Code;
            $count = 1;
            while ($i + $count < count($items) && $items[$i + $count]->SK_Code === $sk) {
                $count++;
            }
            $rowspans[$i] = $count;
            for ($j = 1; $j < $count; $j++) {
                $rowspans[$i + $j] = 0;
            }
            $i += $count;
        }
    @endphp
    <div class="mochi-table-card relative">
        <div wire:loading.flex wire:target="search,filterYear,filterRound,filterMonth,kpiFilter,showAllTeachers,showExtendedColumns,setKpiFilter,resetFilters,clearSearch,clearRoundFilter,clearMonthFilter,clearKpiFilter,openEditModal,saveEditForm"
             class="absolute inset-0 z-20 hidden items-center justify-center bg-white/70 backdrop-blur-[1px]">
            <div class="flex items-center gap-2 rounded-full border border-mochi-header/20 bg-white px-3 py-2 text-sm font-medium text-mochi-header shadow-sm">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                목록을 불러오는 중
            </div>
        </div>
        <div class="md:hidden space-y-3 p-3">
            @forelse($items as $teacher)
                @php
                    $canOpenEditModal = $this->canOpenEditModal($teacher->ID);
                @endphp
                @include('partials.coach.teacher-support-mobile-card', [
                    'teacher' => $teacher,
                    'cols' => $cols,
                    'canOpenEditModal' => $canOpenEditModal,
                ])
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                    조건에 맞는 교사가 없습니다.
                </div>
            @endforelse
        </div>

        <div class="hidden md:block overflow-x-auto isolate">
            <table class="coach-teacher-support-table w-full text-sm whitespace-nowrap">
                <colgroup>
                    <col class="coach-support-col-sk">
                    <col class="coach-support-col-institution">
                    <col class="coach-support-col-position">
                    <col class="coach-support-col-name">
                    <col class="coach-support-col-plan1-date">
                </colgroup>
                <thead class="mochi-table-head">
                <tr class="text-gray-700">
                    <th class="coach-support-sticky-sk coach-support-sticky-sk--head coach-support-sk-code px-3 py-2 text-center">SK_Code</th>
                    <th class="coach-support-sticky-inst coach-support-sticky-inst--head px-3 py-2 text-center">기관명</th>
                    <th class="coach-support-sticky-position coach-support-sticky-position--head px-3 py-2 text-left">직급</th>
                    <th class="coach-support-sticky-name coach-support-sticky-name--head px-3 py-2 text-left">이름</th>
                    <th class="coach-support-sticky-plan1-date coach-support-sticky-plan1-date--head px-3 py-2 text-left">1차 지원 계획일자</th>
                    <th class="px-3 py-2 text-left">1차 지원 계획타입</th>
                    <th class="px-3 py-2 text-left">2차 지원 계획일자</th>
                    <th class="px-3 py-2 text-left">2차 지원 계획타입</th>
                    <th class="px-3 py-2 text-left">1차 지원 완료일</th>
                    <th class="px-3 py-2 text-left">1차 완료 타입</th>
                    <th class="px-3 py-2 text-left">2차 지원 완료일</th>
                    <th class="px-3 py-2 text-left">2차 완료 타입</th>
                    @if($showExtendedColumns)
                        <th class="px-3 py-2 text-left">3차 완료</th>
                        <th class="px-3 py-2 text-left">4차 완료</th>
                        <th class="px-3 py-2 text-left">GS Essentials</th>
                        <th class="px-3 py-2 text-left">LS Essentials</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @forelse($items as $idx => $teacher)
                    @php
                        $isFirstInGroup = ($rowspans[$idx] ?? 0) > 0;
                        $span = $rowspans[$idx] ?? 0;
                        $canOpenEditModal = $this->canOpenEditModal($teacher->ID);
                    @endphp
                    <tr wire:key="teacher-{{ $teacher->ID }}"
                        class="mochi-table-row-hover">
                        @if($isFirstInGroup)
                            <td class="coach-support-sticky-sk coach-support-sk-code px-3 py-2 align-middle text-center font-mono text-xs text-purple-700"
                                rowspan="{{ $span }}">
                                {{ ltrim((string) $teacher->SK_Code, '*') }}
                            </td>
                            <td class="coach-support-sticky-inst px-3 py-2 align-middle text-center"
                                rowspan="{{ $span }}">
                                <button type="button"
                                        class="coach-support-inst-link cursor-pointer text-center text-mochi-header underline hover:text-mochi-header/80"
                                        wire:click.stop="openInstitutionModal('{{ $teacher->SK_Code }}')">
                                    {{ $teacher->institution?->resolvedAccountName() ?: $teacher->School_Name }}
                                </button>
                            </td>
                        @endif
                        <td class="coach-support-sticky-position px-3 py-2 align-middle">
                            @if($teacher->Position)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                    {{ $teacher->isRetired() ? 'bg-red-100 text-red-800' : 'text-gray-700' }}">
                                    {{ $teacher->Position }}
                                </span>
                            @endif
                        </td>
                        <td class="coach-support-sticky-name px-3 py-2 align-middle">
                            <button type="button"
                                    class="coach-support-name-link cursor-pointer text-left text-mochi-header underline hover:text-mochi-header/80"
                                    wire:click.stop="openTeacherModal({{ $teacher->ID }})">
                                    {{ $teacher->Name }}
                            </button>
                        </td>
                        <td class="coach-support-sticky-plan1-date px-3 py-2 align-middle coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            <div class="inline-flex items-center gap-1.5">
                                <span>{{ \App\Support\ExcelSerialDate::formatPlanMonth($teacher->{$cols['plan_1st']}) }}</span>
                                @if($canOpenEditModal)
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ $teacher->{$cols['plan_type_1st']} }}
                        </td>
                        <td class="px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ \App\Support\ExcelSerialDate::formatPlanMonth($teacher->{$cols['plan_2nd']}) }}
                        </td>
                        <td class="px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ $teacher->{$cols['plan_type_2nd']} }}
                        </td>
                        <td class="px-3 py-2 coach-support-schedule-cell {{ $teacher->{$cols['completed_1st']} ? 'bg-green-50' : '' }} {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ \App\Support\ExcelSerialDate::toStorageString($teacher->getRawOriginal($cols['completed_1st'])) }}
                        </td>
                        <td class="px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ $teacher->{$cols['type_1st']} }}
                        </td>
                        <td class="px-3 py-2 coach-support-schedule-cell {{ $teacher->{$cols['completed_2nd']} ? 'bg-green-50' : '' }} {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ \App\Support\ExcelSerialDate::toStorageString($teacher->getRawOriginal($cols['completed_2nd'])) }}
                        </td>
                        <td class="px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ $teacher->{$cols['type_2nd']} }}
                        </td>
                        @if($showExtendedColumns)
                            <td class="px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                                @if($canOpenEditModal)
                                    wire:click="openEditModal({{ $teacher->ID }})"
                                    role="button"
                                    tabindex="0"
                                    aria-label="{{ $teacher->Name }} 지원 일정 수정"
                                @endif>
                                {{ \App\Support\ExcelSerialDate::toStorageString($teacher->getRawOriginal($cols['completed_3rd'])) }}
                            </td>
                            <td class="px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                                @if($canOpenEditModal)
                                    wire:click="openEditModal({{ $teacher->ID }})"
                                    role="button"
                                    tabindex="0"
                                    aria-label="{{ $teacher->Name }} 지원 일정 수정"
                                @endif>
                                {{ \App\Support\ExcelSerialDate::toStorageString($teacher->getRawOriginal($cols['completed_4th'])) }}
                            </td>
                            <td class="px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                                @if($canOpenEditModal)
                                    wire:click="openEditModal({{ $teacher->ID }})"
                                    role="button"
                                    tabindex="0"
                                    aria-label="{{ $teacher->Name }} 지원 일정 수정"
                                @endif>
                                {{ \App\Support\ExcelSerialDate::toStorageString($teacher->getRawOriginal($cols['essentials_gs'])) }}
                            </td>
                            <td class="px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                                @if($canOpenEditModal)
                                    wire:click="openEditModal({{ $teacher->ID }})"
                                    role="button"
                                    tabindex="0"
                                    aria-label="{{ $teacher->Name }} 지원 일정 수정"
                                @endif>
                                {{ \App\Support\ExcelSerialDate::toStorageString($teacher->getRawOriginal($cols['essentials_ls'])) }}
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showExtendedColumns ? 16 : 12 }}" class="px-4 py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p>조건에 맞는 교사가 없습니다.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($teachers->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $teachers->links() }}
            </div>
        @endif
    </div>

    {{-- Institution Info Modal --}}
    @if($showInstitutionModal && $institutionInfo)
        <div class="mochi-modal-overlay" wire:click.self="closeInstitutionModal">
            <div class="mochi-modal-shell max-w-4xl max-h-[min(90vh,calc(100dvh-2rem))] min-h-0 flex flex-col" @click.stop>
                <x-admin.modal-header title="TR 기관정보조회" close-action="closeInstitutionModal" />
                <div class="mochi-modal-body-scroll px-6 py-4 space-y-6">

                    {{-- 기관정보 --}}
                    <div>
                        <h4 class="mb-3 text-base font-semibold text-mochi-header">기관정보</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">기관명:</span>
                                <span class="flex-1 rounded-lg bg-gray-50 px-3 py-1.5 text-gray-800">{{ $institutionInfo['name'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">Consultant:</span>
                                <span class="flex-1 rounded-lg bg-gray-50 px-3 py-1.5 text-gray-800">{{ $institutionInfo['co'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">주소:</span>
                                <span class="flex-1 rounded-lg bg-gray-50 px-3 py-1.5 text-gray-800">{{ $institutionInfo['address'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">CS:</span>
                                <span class="flex-1 rounded-lg bg-gray-50 px-3 py-1.5 text-gray-800">{{ $institutionInfo['cs'] }}</span>
                            </div>
                            <div></div>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">Coach:</span>
                                <span class="flex-1 rounded-lg bg-gray-50 px-3 py-1.5 text-gray-800">{{ $institutionInfo['tr'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- 기관 지원 내역 --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">기관 지원 내역</h4>
                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead class="mochi-table-head text-gray-700">
                                <tr>
                                    <th class="px-2 py-1.5 text-left border-b">ID</th>
                                    <th class="px-2 py-1.5 text-left border-b">담당자</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원날짜</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 타입</th>
                                    <th class="px-2 py-1.5 text-left border-b">기관이슈</th>
                                    <th class="px-2 py-1.5 text-left border-b">상태</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($institutionSupportHistory as $record)
                                    <tr @class([
                                        'border-b border-gray-100',
                                        'hover:bg-blue-50 cursor-pointer' => !empty($record['detail_key']),
                                        'hover:bg-gray-50' => empty($record['detail_key']),
                                    ])
                                        @if(!empty($record['detail_key']))
                                            wire:click.stop="openTeacherSupportHistoryDetail('{{ $record['detail_key'] }}', {{ $record['teacher_id'] ?? 'null' }})"
                                        @endif>
                                        <td class="px-2 py-1.5">{{ $record['id'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['coach'] }}</td>
                                        <td class="px-2 py-1.5 @if(!empty($record['detail_key'])) text-mochi-header underline @endif">{{ $record['date'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['type'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['issue'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['status'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-2 py-3 text-center text-gray-400">내역 없음</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- 교사 지원 내역 --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">교사 지원 내역:</h4>
                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead class="mochi-table-head text-gray-700">
                                <tr>
                                    <th class="px-2 py-1.5 text-left border-b">ID</th>
                                    <th class="px-2 py-1.5 text-left border-b">담당 코치</th>
                                    <th class="px-2 py-1.5 text-left border-b">교사명</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 날짜</th>
                                    <th class="px-2 py-1.5 text-left border-b">상태</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 타입</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($teacherSupportHistory as $record)
                                    <tr @class([
                                        'border-b border-gray-100',
                                        'hover:bg-blue-50 cursor-pointer' => !empty($record['detail_key']),
                                        'hover:bg-gray-50' => empty($record['detail_key']),
                                    ])
                                        @if(!empty($record['detail_key']))
                                            wire:click.stop="openTeacherSupportHistoryDetail('{{ $record['detail_key'] }}', {{ $record['teacher_id'] ?? 'null' }})"
                                        @endif>
                                        <td class="px-2 py-1.5">{{ $record['id'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['coach'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['teacher'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['date'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['status'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['type'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-2 py-3 text-center text-gray-400">내역 없음</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Contacts --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Contacts:</h4>
                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead class="mochi-table-head text-gray-700">
                                <tr>
                                    <th class="px-2 py-1.5 text-left border-b">ID</th>
                                    <th class="px-2 py-1.5 text-left border-b">이름</th>
                                    <th class="px-2 py-1.5 text-left border-b">연락처</th>
                                    <th class="px-2 py-1.5 text-left border-b">이메일</th>
                                    <th class="px-2 py-1.5 text-left border-b">GrapeSEED</th>
                                    <th class="px-2 py-1.5 text-left border-b">LittleSEED</th>
                                    <th class="px-2 py-1.5 text-left border-b">마지막 지원날짜</th>
                                    <th class="px-2 py-1.5 text-left border-b">마지막 지원타입</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($institutionContacts as $contact)
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="px-2 py-1.5">{{ $contact['id'] }}</td>
                                        <td class="px-2 py-1.5 {{ $contact['position'] === '원장' ? 'bg-pink-100' : '' }}">
                                            {{ $contact['name'] }}
                                        </td>
                                        <td class="px-2 py-1.5">{{ $contact['phone'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['email'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['gs_essentials'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['ls_essentials'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['last_support_date'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['last_support_type'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="px-2 py-3 text-center text-gray-400">연락처 없음</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endif

    {{-- Edit Modal --}}
    @if($showEditModal && $editingTeacherId)
        <div class="mochi-modal-overlay" wire:click.self="closeEditModal">
            <div class="mochi-modal-shell max-w-2xl max-h-[min(90vh,calc(100dvh-2rem))] min-h-0 flex flex-col" wire:key="coach-edit-modal-{{ $editingTeacherId }}" @click.stop>
                <x-admin.modal-header title="지원 일정 수정" close-action="closeEditModal" />
                <div class="mochi-modal-body-scroll px-6 py-4 space-y-6">
                    {{-- 1·2차 계획 --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">계획</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">1차 계획일</label>
                                <input type="date" wire:model="editForm.plan_1st"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">1차 계획 타입</label>
                                <select wire:model="editForm.plan_type_1st"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    <option value="">-</option>
                                    @if(filled($editForm['plan_type_1st'] ?? '') && ! in_array($editForm['plan_type_1st'], $planSupportTypes, true))
                                        <option value="{{ $editForm['plan_type_1st'] }}">{{ $editForm['plan_type_1st'] }}</option>
                                    @endif
                                    @foreach($planSupportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">2차 계획일</label>
                                <input type="date" wire:model="editForm.plan_2nd"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">2차 계획 타입</label>
                                <select wire:model="editForm.plan_type_2nd"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    <option value="">-</option>
                                    @if(filled($editForm['plan_type_2nd'] ?? '') && ! in_array($editForm['plan_type_2nd'], $planSupportTypes, true))
                                        <option value="{{ $editForm['plan_type_2nd'] }}">{{ $editForm['plan_type_2nd'] }}</option>
                                    @endif
                                    @foreach($planSupportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- 1·2차 완료 --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">완료</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">1차 완료일</label>
                                <input type="date"
                                       wire:key="edit-completed-1st-{{ $editingTeacherId }}"
                                       wire:model="editForm.completed_1st"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">1차 타입</label>
                                <select wire:model="editForm.type_1st"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    <option value="">-</option>
                                    @if(filled($editForm['type_1st'] ?? '') && ! in_array($editForm['type_1st'], $completionSupportTypes, true))
                                        <option value="{{ $editForm['type_1st'] }}">{{ $editForm['type_1st'] }}</option>
                                    @endif
                                    @foreach($completionSupportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">2차 완료일</label>
                                <input type="date"
                                       wire:key="edit-completed-2nd-{{ $editingTeacherId }}"
                                       wire:model="editForm.completed_2nd"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">2차 타입</label>
                                <select wire:model="editForm.type_2nd"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    <option value="">-</option>
                                    @if(filled($editForm['type_2nd'] ?? '') && ! in_array($editForm['type_2nd'], $completionSupportTypes, true))
                                        <option value="{{ $editForm['type_2nd'] }}">{{ $editForm['type_2nd'] }}</option>
                                    @endif
                                    @foreach($completionSupportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- 3·4차 완료 --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">3·4차 완료</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">3차 완료일</label>
                                <input type="date"
                                       wire:key="edit-completed-3rd-{{ $editingTeacherId }}"
                                       wire:model="editForm.completed_3rd"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">3차 타입</label>
                                <select wire:model="editForm.type_3rd"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    <option value="">-</option>
                                    @if(filled($editForm['type_3rd'] ?? '') && ! in_array($editForm['type_3rd'], $completionSupportTypes, true))
                                        <option value="{{ $editForm['type_3rd'] }}">{{ $editForm['type_3rd'] }}</option>
                                    @endif
                                    @foreach($completionSupportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">4차 완료일</label>
                                <input type="date"
                                       wire:key="edit-completed-4th-{{ $editingTeacherId }}"
                                       wire:model="editForm.completed_4th"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">4차 타입</label>
                                <select wire:model="editForm.type_4th"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    <option value="">-</option>
                                    @if(filled($editForm['type_4th'] ?? '') && ! in_array($editForm['type_4th'], $completionSupportTypes, true))
                                        <option value="{{ $editForm['type_4th'] }}">{{ $editForm['type_4th'] }}</option>
                                    @endif
                                    @foreach($completionSupportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Essentials --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Essentials</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">GrapeSEED Essentials</label>
                                <input type="date" wire:model="editForm.essentials_gs"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">LittleSEED Essentials</label>
                                <input type="date" wire:model="editForm.essentials_ls"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t">
                    <button wire:click="closeEditModal"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        취소
                    </button>
                    <button wire:click="saveEditForm"
                            class="rounded-lg bg-mochi-header px-4 py-2 text-sm text-white hover:bg-mochi-header/90">
                        저장
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Teacher Detail Modal --}}
    @if($showTeacherModal && $teacherDetailInfo)
        <div class="mochi-modal-overlay" wire:click.self="closeTeacherModal">
            <div class="mochi-modal-shell max-w-4xl max-h-[min(90vh,calc(100dvh-2rem))] min-h-0 flex flex-col" @click.stop>
                <x-admin.modal-header title="TR 교사정보">
                    <x-slot:actions>
                        @if(! $teacherModalEditMode && $teacherDetailInfo['class_in_out'])
                            <button type="button"
                                    wire:click="confirmRetireTeacher"
                                    class="cursor-pointer rounded-lg border border-red-300 px-3 py-1.5 text-xs text-red-700 hover:bg-red-50">
                                퇴직
                            </button>
                            <button type="button"
                                    wire:click="startTeacherEdit"
                                    class="cursor-pointer rounded-lg border border-amber-300 px-3 py-1.5 text-xs text-amber-700 hover:bg-amber-50">
                                수정
                            </button>
                        @endif
                        <button type="button"
                                wire:click="closeTeacherModal"
                                class="cursor-pointer text-gray-400 transition-colors hover:text-gray-600"
                                aria-label="닫기">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </x-slot:actions>
                </x-admin.modal-header>
                <div class="mochi-modal-body-scroll px-6 py-4 space-y-6">

                    {{-- 퇴직 확인 --}}
                    @if($confirmingRetire)
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-sm text-red-800 font-medium mb-3">정말 이 교사를 퇴직 처리하시겠습니까? 이 작업은 되돌릴 수 없습니다.</p>
                            <div class="flex gap-2">
                                <button wire:click="retireTeacher"
                                        class="px-3 py-1.5 text-xs text-white bg-red-600 rounded hover:bg-red-700 cursor-pointer">
                                    퇴직 확인
                                </button>
                                <button wire:click="cancelRetireTeacher"
                                        class="px-3 py-1.5 text-xs text-gray-600 border border-gray-300 rounded hover:bg-gray-50 cursor-pointer">
                                    취소
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- 수정 모드 --}}
                    @if($teacherModalEditMode)
                        <div>
                            <h4 class="text-base font-semibold text-gray-800 mb-3">교사 정보 수정</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">이름</label>
                                    <input type="text" wire:model="teacherProfileForm.name"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">이메일</label>
                                    <input type="email" wire:model="teacherProfileForm.email"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">전화</label>
                                    <input type="text" wire:model="teacherProfileForm.phone"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">직급</label>
                                    <input type="text" wire:model="teacherProfileForm.position"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">비고</label>
                                    <textarea wire:model="teacherProfileForm.description" rows="2"
                                              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header"></textarea>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-500">수업참여</label>
                                    <input type="checkbox" wire:model="teacherProfileForm.class_in_out"
                                           class="rounded border-gray-300 text-mochi-header focus:ring-mochi-header">
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-4">
                                <button wire:click="$set('teacherModalEditMode', false)"
                                        class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                                    취소
                                </button>
                                <button wire:click="saveTeacherProfile"
                                        class="cursor-pointer rounded-lg bg-mochi-header px-4 py-2 text-sm text-white hover:bg-mochi-header/90">
                                    저장
                                </button>
                            </div>
                        </div>
                    @else
                    <div>
                        <h4 class="text-base font-semibold text-gray-800 mb-3">교사 정보</h4>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    <tr>
                                        <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">이름</th>
                                        <td class="px-3 py-2 font-medium text-gray-900">{{ $teacherDetailInfo['name'] ?? '-' }}</td>
                                        <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">GrapeSEED 이수</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['gs_essentials'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">이메일</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['email'] ?? '-' }}</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">LittleSEED 이수</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['ls_essentials'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">전화</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['phone'] ?? '-' }}</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 Coach</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['tr'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">직급</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['position'] ?? '-' }}</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">CS</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['cs'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">수업참여</th>
                                        <td class="px-3 py-2">
                                            @if($teacherDetailInfo['class_in_out'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">수업(O)</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">수업(X)</span>
                                            @endif
                                        </td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">CO</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['co'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['school_name'] ?? '-' }} ({{ $teacherDetailInfo['sk_code'] }})</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium"></th>
                                        <td></td>
                                    </tr>
                                    @if($teacherDetailInfo['description'])
                                        <tr>
                                            <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">비고</th>
                                            <td colspan="3" class="px-3 py-2 text-gray-900 whitespace-pre-wrap">{{ $teacherDetailInfo['description'] }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    {{-- 지원 내역 --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">지원 내역</h4>
                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead class="mochi-table-head text-gray-700">
                                <tr>
                                    <th class="px-2 py-1.5 text-left border-b">ID</th>
                                    <th class="px-2 py-1.5 text-left border-b">담당자</th>
                                    <th class="px-2 py-1.5 text-left border-b">교사명</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 날짜</th>
                                    <th class="px-2 py-1.5 text-left border-b">상태</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 타입</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($teacherDetailHistory as $record)
                                    <tr @class([
                                        'border-b border-gray-100',
                                        'hover:bg-blue-50 cursor-pointer' => !empty($record['detail_key']),
                                        'hover:bg-gray-50' => empty($record['detail_key']),
                                    ])
                                        @if(!empty($record['detail_key']))
                                            wire:click.stop="openTeacherSupportHistoryDetail('{{ $record['detail_key'] }}', {{ $record['teacher_id'] ?? $teacherDetailInfo['id'] }})"
                                        @endif>
                                        <td class="px-2 py-1.5">{{ $record['id'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['coach'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['teacher'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['date'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['status'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['type'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-2 py-3 text-center text-gray-400">지원 내역 없음</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- 새 지원 Pill (지원 내역 유무·수업 O/X와 무관 — 퇴직·수정 모드만 제외) --}}
                    @if(!$teacherModalEditMode)
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">교사 지원 신규 작성:</h4>
                            @unless($teacherDetailInfo['class_in_out'])
                                <p class="text-xs text-gray-500 mb-2">수업 미참여(수업 X) 교사도 지원 보고서를 작성할 수 있습니다.</p>
                            @endunless
                            <div class="grid grid-cols-2 gap-2">
                                @foreach(config('coach_teacher_support_create.types', []) as $pill)
                                    @php
                                        $pillLabel = is_array($pill) ? ($pill['label'] ?? '') : (string) $pill;
                                        $pillAction = is_array($pill) ? ($pill['action'] ?? 'support_create') : 'support_create';
                                    @endphp
                                    @if($pillLabel === '')
                                        @continue
                                    @endif
                                    @if($pillAction === 'demo_lesson')
                                        <button type="button"
                                                wire:click.stop="openDemoLessonModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'lva_fr')
                                        <button type="button"
                                                wire:click.stop="openLvaFrModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'lva_fb')
                                        <button type="button"
                                                wire:click.stop="openLvaFbModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'ls_onsite_lva')
                                        <button type="button"
                                                wire:click.stop="openLsOnsiteLvaModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'littleseed_con')
                                        <button type="button"
                                                wire:click.stop="openLittleseedConModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'onsite')
                                        <button type="button"
                                                wire:click.stop="openOnsiteModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'pro_con')
                                        <button type="button"
                                                wire:click.stop="openProConModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'open_class')
                                        <button type="button"
                                                wire:click.stop="openOpenClassModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'unit21_plus')
                                        <button type="button"
                                                wire:click.stop="openUnit21PlusModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'unit31_plus')
                                        <button type="button"
                                                wire:click.stop="openUnit31PlusModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>
                <div class="px-6 py-4 bg-gray-50 border-t flex justify-end">
                    <button wire:click="closeTeacherModal"
                            class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 cursor-pointer">
                        닫기
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div wire:loading.delay
         wire:target="openEditModal,saveEditForm"
         class="fixed bottom-6 right-6 z-50">
        <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-lg">
            <svg class="h-4 w-4 animate-spin text-mochi-header" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            처리 중...
        </div>
    </div>

    @include('components.coach.demo-lesson-support-modal', ['demoLessonConfig' => $demoLessonConfig])
    @include('components.coach.lva-fr-support-modal', ['lvaFrConfig' => $lvaFrConfig])
    @include('components.coach.lva-fb-support-modal', ['lvaFbConfig' => $lvaFbConfig])
    @include('components.coach.ls-onsite-lva-support-modal', ['lsOnsiteLvaConfig' => $lsOnsiteLvaConfig])
    @include('components.coach.littleseed-con-support-modal', ['littleseedConConfig' => $littleseedConConfig])
    @include('components.coach.onsite-support-modal', ['onsiteConfig' => $onsiteConfig])
    @include('components.coach.pro-con-support-modal', ['proConConfig' => $proConConfig])
    @include('components.coach.open-class-support-modal', ['openClassConfig' => $openClassConfig])
    @include('components.coach.unit21-plus-support-modal', ['unit21PlusConfig' => $unit21PlusConfig])
    @include('components.coach.unit31-plus-support-modal', ['unit31PlusConfig' => $unit31PlusConfig])

    <x-coach.teacher-support-history-detail-modal
        :show="$showTeacherSupportHistoryDetailModal"
        :detail="$selectedTeacherSupportHistoryDetail"
    />
</div>
