<div class="mochi-page" x-data @visit-support-show-alert.window="alert($event.detail.message)">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm" data-mochi-flash-dismiss="3000" role="status">
            {{ session('success') }}
        </div>
    @endif

    {{-- Summary --}}
    <div class="mochi-summary-card">
        @php
            $kpiToggleLabels = \App\Support\TeacherSupportKpiCalculator::visibleToggleLabels();
            $totalSupportCount = \App\Support\TeacherSupportKpiCalculator::totalSupportCount($kpis);
            $unsupportedTeacherCount = max(0, (int) ($kpis['teacher_count'] ?? 0) - (int) ($kpis['any_completed'] ?? 0));
        @endphp

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <div class="mochi-toggle-group">
                <span class="mochi-toggle-btn cursor-default pointer-events-none text-gray-700"
                      aria-label="기관 수">
                    기관 수
                    <span class="font-semibold">{{ $kpis['institution_count'] ?? 0 }}</span>
                </span>
                <span class="mochi-toggle-btn cursor-default pointer-events-none text-gray-700"
                      aria-label="교사 수">
                    교사 수
                    <span class="font-semibold">{{ $kpis['teacher_count'] ?? 0 }}</span>
                </span>
            </div>
            <span data-kpi-divider aria-hidden="true" class="h-6 w-px bg-gray-200"></span>

            <div class="mochi-toggle-group">
                <button type="button"
                        wire:click="setKpiFilter('any_completed')"
                        aria-pressed="{{ $kpiFilter === 'any_completed' ? 'true' : 'false' }}"
                        aria-label="지원함 교사 수"
                        title="선택 연도에 1~4차 중 하나라도 완료한 교사"
                        class="mochi-toggle-btn {{ $kpiFilter === 'any_completed' ? 'mochi-toggle-btn--active-success' : 'text-green-800' }}">
                    지원함
                    <span class="font-semibold">{{ $kpis['any_completed'] ?? 0 }}</span>
                </button>
                <button type="button"
                        wire:click="setKpiFilter('never_supported')"
                        aria-pressed="{{ $kpiFilter === 'never_supported' ? 'true' : 'false' }}"
                        aria-label="미지원 교사 수"
                        title="선택 연도에 완료일이 없는 교사"
                        class="mochi-toggle-btn {{ $kpiFilter === 'never_supported' ? 'mochi-toggle-btn--active-danger' : '' }}">
                    미지원
                    <span class="font-semibold">{{ $unsupportedTeacherCount }}</span>
                </button>
            </div>
            <span data-kpi-divider aria-hidden="true" class="h-6 w-px bg-gray-200"></span>

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
            <span data-kpi-divider aria-hidden="true" class="h-6 w-px bg-gray-200"></span>

            <div class="flex flex-wrap items-center gap-2">
                <div class="mochi-toggle-group">
                    <span class="mochi-toggle-btn cursor-default pointer-events-none text-gray-700"
                          aria-label="총 지원 횟수">
                        총 지원 횟수
                        <span class="font-semibold">{{ $totalSupportCount }}</span>
                    </span>
                </div>
                <span wire:loading.delay
                      wire:loading.class.remove="hidden"
                      wire:loading.class="inline-flex"
                      wire:target="search,filterYear,filterRound,filterMonth,filterCoach,filterPosition,filterEmploymentType,kpiFilter,showAllTeachers,showExtendedColumns,setKpiFilter,resetFilters"
                      class="hidden items-center gap-1.5 rounded-full border border-mochi-header/20 bg-mochi-header/5 px-2.5 py-1 text-xs font-medium text-mochi-header">
                    <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    목록 갱신 중
                </span>
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <select wire:model.live="filterYear"
                        wire:key="teacher-support-year-filter"
                        class="py-1.5 px-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    <option value="">전체</option>
                    @foreach($yearFilterOptions as $y)
                        <option value="{{ $y }}">{{ $y }}년</option>
                    @endforeach
                </select>
                <a href="{{ \App\Support\TeamMenuContext::route('supports.create') }}"
                   class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600">
                    기관지원보고서
                </a>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mochi-filter-card">
        <div class="flex flex-nowrap items-center gap-3 overflow-x-auto pb-1 text-sm">
                <div class="hidden flex items-center gap-2 text-sm" aria-hidden="true">
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

                @if($coachFilterOptions->isNotEmpty())
                    <select wire:model.live="filterCoach"
                            class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                        <option value="">전체 담당</option>
                        @foreach($coachFilterOptions as $coachName)
                            <option value="{{ $coachName }}">{{ $coachName }}</option>
                        @endforeach
                    </select>
                @endif

                <select wire:model.live="filterMonth"
                        class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    <option value="">전체</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ $m }}월</option>
                    @endfor
                </select>

                <div class="relative min-w-0 flex-1 md:min-w-[14rem] max-w-xl">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.500ms="search"
                           placeholder="이름, 기관명, SK코드 검색"
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                </div>

                @if($search || $filterRound || $filterMonth || $filterCoach || $kpiFilter || $filterPosition === '' || $filterEmploymentType !== '')
                    <button wire:click="resetFilters"
                            class="shrink-0 py-2 px-3 text-sm text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                        초기화
                    </button>
                @endif
                <div class="flex shrink-0 items-center gap-2.5 whitespace-nowrap">
                    <span class="text-gray-500">직급</span>
                    <div class="mochi-toggle-group">
                        <button type="button" wire:click="$set('filterPosition', 'teacher')"
                                class="mochi-toggle-btn {{ $filterPosition === 'teacher' ? 'mochi-toggle-btn--active' : '' }}">
                            교사만
                        </button>
                        <button type="button" wire:click="$set('filterPosition', '')"
                                class="mochi-toggle-btn {{ $filterPosition === '' ? 'mochi-toggle-btn--active' : '' }}">
                            전체
                        </button>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2.5 whitespace-nowrap">
                    <span class="text-gray-500">근무형태</span>
                    <div class="mochi-toggle-group">
                        <button type="button" wire:click="$set('filterEmploymentType', '')"
                                class="mochi-toggle-btn {{ $filterEmploymentType === '' ? 'mochi-toggle-btn--active' : '' }}">
                            전체
                        </button>
                        <button type="button"
                                title="{{ \App\Enums\TeacherEmploymentType::FullTime->label() }}"
                                wire:click="$set('filterEmploymentType', '{{ \App\Enums\TeacherEmploymentType::FullTime->value }}')"
                                class="mochi-toggle-btn {{ $filterEmploymentType === \App\Enums\TeacherEmploymentType::FullTime->value ? 'mochi-toggle-btn--active' : '' }}">
                            풀타임
                        </button>
                        <button type="button"
                                title="{{ \App\Enums\TeacherEmploymentType::PartTime->label() }}"
                                wire:click="$set('filterEmploymentType', '{{ \App\Enums\TeacherEmploymentType::PartTime->value }}')"
                                class="mochi-toggle-btn {{ $filterEmploymentType === \App\Enums\TeacherEmploymentType::PartTime->value ? 'mochi-toggle-btn--active' : '' }}">
                            파트
                        </button>
                        <button type="button" wire:click="$set('filterEmploymentType', '{{ \App\Enums\TeacherEmploymentType::Unspecified->value }}')"
                                class="mochi-toggle-btn {{ $filterEmploymentType === \App\Enums\TeacherEmploymentType::Unspecified->value ? 'mochi-toggle-btn--active' : '' }}">
                            미지정
                        </button>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2.5 whitespace-nowrap">
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
        </div>
        <p class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-xs text-gray-500">
            <span>계획·완료 칸을 클릭하면 일정을 수정할 수 있습니다.</span>
            <span class="text-gray-400">전체 기관 기준 조회</span>
        </p>
    </div>

    {{-- Table --}}
    @php
        $items = $teachers->items();
        $cols = config('coach_teacher_support.columns');
        // 보이는 열만 센다. 숨긴 계획 열을 colspan에 넣으면 필터 시 헤더 너비가 깨진다.
        $tableColumnSpan = 12;
    @endphp
    <div class="mochi-table-card relative">
        <div class="md:hidden space-y-3 p-3">
            @forelse($items as $teacher)
                @php
                    // 지원 일정 수정 모달 임시 비활성화. 복구 시 아래 주석을 해제할 것.
                    // $canOpenEditModal = $this->canOpenEditModal($teacher->ID);
                    $canOpenEditModal = false;
                @endphp
                @include('partials.coach.teacher-support-mobile-card', [
                    'teacher' => $teacher,
                    'cols' => $cols,
                    'canOpenEditModal' => $canOpenEditModal,
                    'showExtendedColumns' => $showExtendedColumns,
                    'displayYear' => $displayYear,
                ])
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                    조건에 맞는 교사가 없습니다.
                </div>
            @endforelse
        </div>

        <div class="coach-teacher-support-table-scroll hidden md:block isolate">
            <table @class([
                'coach-teacher-support-table w-full text-xs',
                'coach-teacher-support-table--extended' => $showExtendedColumns,
            ])>
                <colgroup>
                    <col class="coach-support-col-sk">
                    <col class="coach-support-col-institution">
                    <col class="coach-support-col-name">
                    <col class="coach-support-col-position">
                    <col class="coach-support-col-employment">
                    <col class="coach-support-col-essentials-gs">
                    <col class="coach-support-col-essentials-ls">
                    <col class="coach-support-col-new-teacher-support">
                    <col span="4" class="coach-support-col-completed">
                </colgroup>
                <thead class="mochi-table-head">
                <tr class="text-gray-700">
                    <th class="coach-support-sticky-sk coach-support-sticky-sk--head coach-support-sk-code px-2 py-1.5 text-center">SK</th>
                    <th class="coach-support-sticky-inst coach-support-sticky-inst--head px-2 py-1.5 text-center">기관명</th>
                    <th class="coach-support-sticky-name coach-support-sticky-name--head px-2 py-1.5 text-left">이름</th>
                    <th class="coach-support-sticky-position coach-support-sticky-position--head px-2 py-1.5 text-left">직급</th>
                    <th class="coach-support-sticky-employment coach-support-sticky-employment--head px-2 py-1.5 text-left">근무형태</th>
                    <th class="coach-support-sticky-essentials-gs coach-support-sticky-essentials-gs--head px-2 py-1.5 text-left" title="GS Essentials">GS Ess.</th>
                    <th class="coach-support-sticky-essentials-ls coach-support-sticky-essentials-ls--head px-2 py-1.5 text-left" title="LS Essentials">LS Ess.</th>
                    <th class="coach-support-sticky-new-teacher-support coach-support-sticky-new-teacher-support--head px-2 py-1.5 text-left">신규교사 지원</th>
                    <th class="coach-support-col-plan-12-hidden px-3 py-2 text-left coach-support-col-plan1-date">1차 지원 계획일자</th>
                    <th class="coach-support-col-plan-12-hidden px-3 py-2 text-left">1차 지원 계획타입</th>
                    <th class="coach-support-col-plan-12-hidden px-3 py-2 text-left">2차 지원 계획일자</th>
                    <th class="coach-support-col-plan-12-hidden px-3 py-2 text-left">2차 지원 계획타입</th>
                    @if($showExtendedColumns)
                        <th class="coach-support-col-plan-34-hidden px-3 py-2 text-left">3차 지원 계획일자</th>
                        <th class="coach-support-col-plan-34-hidden px-3 py-2 text-left">3차 지원 계획타입</th>
                        <th class="coach-support-col-plan-34-hidden px-3 py-2 text-left">4차 지원 계획일자</th>
                        <th class="coach-support-col-plan-34-hidden px-3 py-2 text-left">4차 지원 계획타입</th>
                    @endif
                    <th class="coach-support-col-completed px-2 py-1.5 text-left" title="1차 지원 완료일">1차 완료</th>
                    <th class="coach-support-col-completed px-2 py-1.5 text-left" title="2차 지원 완료일">2차 완료</th>
                    <th class="coach-support-col-completed px-2 py-1.5 text-left" title="3차 지원 완료일">3차 완료</th>
                    <th class="coach-support-col-completed px-2 py-1.5 text-left" title="4차 지원 완료일">4차 완료</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $idx => $teacher)
                    @php
                        $institutionIsTerminated = $teacher->institution?->isTerminatedCustomer() ?? false;
                        // 지원 일정 수정 모달 임시 비활성화. 복구 시 아래 주석을 해제할 것.
                        // $canOpenEditModal = $this->canOpenEditModal($teacher->ID);
                        $canOpenEditModal = false;
                    @endphp
                    <tr wire:key="teacher-{{ $teacher->ID }}"
                        data-group-first="1"
                        class="mochi-table-row-hover">
                        <td class="coach-support-sticky-sk coach-support-sk-code px-3 py-2 align-middle text-center font-mono text-xs text-purple-700">
                            {{ ltrim((string) $teacher->SK_Code, '*') }}
                        </td>
                        <td class="coach-support-sticky-inst px-3 py-2 align-middle text-center">
                            <button type="button"
                                    class="coach-support-inst-link cursor-pointer text-center underline {{ $institutionIsTerminated ? 'coach-support-inst-link--terminated text-red-700 hover:text-red-800' : 'text-mochi-header hover:text-mochi-header/80' }}"
                                    wire:click.stop="openInstitutionModal('{{ $teacher->SK_Code }}')">
                                {{ $teacher->institution?->resolvedAccountName() ?: $teacher->School_Name }}
                            </button>
                        </td>
                        <td class="coach-support-sticky-name px-3 py-2 align-middle">
                            <button type="button"
                                    class="coach-support-name-link cursor-pointer text-left text-mochi-header underline hover:text-mochi-header/80"
                                    wire:click.stop="openTeacherModal({{ $teacher->ID }})">
                                    {{ $teacher->Name }}
                            </button>
                        </td>
                        <td class="coach-support-sticky-position px-3 py-2 align-middle">
                            @if($teacher->Position)
                                <span class="inline-flex items-center whitespace-nowrap px-1.5 py-0.5 rounded text-xs font-medium
                                    {{ $teacher->isRetired() ? 'bg-red-100 text-red-800' : 'text-gray-700' }}">
                                    {{ $teacher->Position }}
                                </span>
                            @endif
                        </td>
                        <td class="coach-support-sticky-employment px-3 py-2 align-middle">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium text-gray-700">
                                {{ \App\Enums\TeacherEmploymentType::fromMixed($teacher->EmploymentType)->label() }}
                            </span>
                        </td>
                        <td class="coach-support-sticky-essentials-gs px-3 py-2 align-middle coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ \App\Support\ExcelSerialDate::displayStorageString($teacher->getRawOriginal($cols['essentials_gs'])) }}
                        </td>
                        <td class="coach-support-sticky-essentials-ls px-3 py-2 align-middle coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ \App\Support\ExcelSerialDate::displayStorageString($teacher->getRawOriginal($cols['essentials_ls'])) }}
                        </td>
                        @php
                            $newTeacherSupportParts = \App\Support\TeacherSupportNewTeacherDisplay::parts($teacher, $displayYear);
                        @endphp
                        <td class="coach-support-sticky-new-teacher-support px-3 py-2 align-middle coach-support-schedule-cell {{ $newTeacherSupportParts['date'] !== '' ? 'bg-green-50' : '' }} {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            <div class="coach-support-new-teacher-cell">
                                <span class="coach-support-new-teacher-date">
                                    {{ $newTeacherSupportParts['date'] !== '' ? $newTeacherSupportParts['date'] : '-' }}
                                </span>
                                @if($newTeacherSupportParts['type'] !== '')
                                    <span class="coach-support-new-teacher-type" title="{{ $newTeacherSupportParts['type'] }}">
                                        {{ $newTeacherSupportParts['type'] }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="coach-support-col-plan-12-hidden px-3 py-2 align-middle coach-support-col-plan1-date coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            <div class="inline-flex items-center gap-1.5">
                                <span>{{ \App\Support\ExcelSerialDate::displayPlanMonth($teacher->getRawOriginal($cols['plan_1st']), $displayYear) }}</span>
                                @if($canOpenEditModal)
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                @endif
                            </div>
                        </td>
                        <td class="coach-support-col-plan-12-hidden px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            @if(\App\Support\ExcelSerialDate::matchesFilterYear($teacher->getRawOriginal($cols['plan_1st']), $displayYear))
                                {{ $teacher->{$cols['plan_type_1st']} }}
                            @endif
                        </td>
                        <td class="coach-support-col-plan-12-hidden px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            {{ \App\Support\ExcelSerialDate::displayPlanMonth($teacher->getRawOriginal($cols['plan_2nd']), $displayYear) }}
                        </td>
                        <td class="coach-support-col-plan-12-hidden px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            @if(\App\Support\ExcelSerialDate::matchesFilterYear($teacher->getRawOriginal($cols['plan_2nd']), $displayYear))
                                {{ $teacher->{$cols['plan_type_2nd']} }}
                            @endif
                        </td>
                        @if($showExtendedColumns)
                            <td class="coach-support-col-plan-34-hidden px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                                @if($canOpenEditModal)
                                    wire:click="openEditModal({{ $teacher->ID }})"
                                    role="button"
                                    tabindex="0"
                                    aria-label="{{ $teacher->Name }} 지원 일정 수정"
                                @endif>
                                {{ \App\Support\ExcelSerialDate::displayPlanMonth($teacher->getRawOriginal($cols['plan_3rd']), $displayYear) }}
                            </td>
                            <td class="coach-support-col-plan-34-hidden px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                                @if($canOpenEditModal)
                                    wire:click="openEditModal({{ $teacher->ID }})"
                                    role="button"
                                    tabindex="0"
                                    aria-label="{{ $teacher->Name }} 지원 일정 수정"
                                @endif>
                                @if(\App\Support\ExcelSerialDate::matchesFilterYear($teacher->getRawOriginal($cols['plan_3rd']), $displayYear))
                                    {{ $teacher->{$cols['plan_type_3rd']} }}
                                @endif
                            </td>
                            <td class="coach-support-col-plan-34-hidden px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                                @if($canOpenEditModal)
                                    wire:click="openEditModal({{ $teacher->ID }})"
                                    role="button"
                                    tabindex="0"
                                    aria-label="{{ $teacher->Name }} 지원 일정 수정"
                                @endif>
                                {{ \App\Support\ExcelSerialDate::displayPlanMonth($teacher->getRawOriginal($cols['plan_4th']), $displayYear) }}
                            </td>
                            <td class="coach-support-col-plan-34-hidden px-3 py-2 coach-support-schedule-cell {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                                @if($canOpenEditModal)
                                    wire:click="openEditModal({{ $teacher->ID }})"
                                    role="button"
                                    tabindex="0"
                                    aria-label="{{ $teacher->Name }} 지원 일정 수정"
                                @endif>
                                @if(\App\Support\ExcelSerialDate::matchesFilterYear($teacher->getRawOriginal($cols['plan_4th']), $displayYear))
                                    {{ $teacher->{$cols['plan_type_4th']} }}
                                @endif
                            </td>
                        @endif
                        <td class="coach-support-col-completed coach-support-schedule-cell {{ \App\Support\TeacherSupportCompletionDisplay::parts($teacher, 1, $displayYear)['date'] !== '' ? 'bg-green-50' : '' }} {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            @include('partials.coach.teacher-support-completed-cell', [
                                'teacher' => $teacher,
                                'round' => 1,
                                'displayYear' => $displayYear,
                            ])
                        </td>
                        <td class="coach-support-col-completed coach-support-schedule-cell {{ \App\Support\TeacherSupportCompletionDisplay::parts($teacher, 2, $displayYear)['date'] !== '' ? 'bg-green-50' : '' }} {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            @include('partials.coach.teacher-support-completed-cell', [
                                'teacher' => $teacher,
                                'round' => 2,
                                'displayYear' => $displayYear,
                            ])
                        </td>
                        <td class="coach-support-col-completed coach-support-schedule-cell {{ \App\Support\TeacherSupportCompletionDisplay::parts($teacher, 3, $displayYear)['date'] !== '' ? 'bg-green-50' : '' }} {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            @include('partials.coach.teacher-support-completed-cell', [
                                'teacher' => $teacher,
                                'round' => 3,
                                'displayYear' => $displayYear,
                            ])
                        </td>
                        <td class="coach-support-col-completed coach-support-schedule-cell {{ \App\Support\TeacherSupportCompletionDisplay::parts($teacher, 4, $displayYear)['date'] !== '' ? 'bg-green-50' : '' }} {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($canOpenEditModal)
                                wire:click="openEditModal({{ $teacher->ID }})"
                                role="button"
                                tabindex="0"
                                aria-label="{{ $teacher->Name }} 지원 일정 수정"
                            @endif>
                            @include('partials.coach.teacher-support-completed-cell', [
                                'teacher' => $teacher,
                                'round' => 4,
                                'displayYear' => $displayYear,
                            ])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $tableColumnSpan }}" class="px-4 py-12 text-center text-gray-400">
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

        @if($institutionGroupPaginator->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                <p class="mb-2 text-xs text-gray-500">
                    교사 기준 · {{ $institutionGroupPaginator->firstItem() }}–{{ $institutionGroupPaginator->lastItem() }}
                    / 전체 {{ $institutionGroupPaginator->total() }}명
                    · 이번 페이지 교사 {{ $teachers->count() }}명
                </p>
                {{ $institutionGroupPaginator->links() }}
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
                                <span class="flex-1 min-w-0 truncate rounded-lg bg-gray-50 px-3 py-1.5 {{ ! empty($institutionInfo['is_terminated']) ? 'text-red-700 font-medium' : 'text-gray-800' }}">
                                    {{ $institutionInfo['name'] }}
                                </span>
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
                    {{-- 1·2차 계획 (필수) + 3·4차 계획 (선택) --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">계획 (1·2차)</h4>
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

                        <div class="mt-4 border-t border-dashed border-gray-200 pt-4">
                            <h4 class="text-sm font-medium text-gray-600 mb-3">3·4차 계획 (선택)</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">3차 계획일</label>
                                    <input type="date" wire:model="editForm.plan_3rd"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">3차 계획 타입</label>
                                    <select wire:model="editForm.plan_type_3rd"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                        <option value="">-</option>
                                        @if(filled($editForm['plan_type_3rd'] ?? '') && ! in_array($editForm['plan_type_3rd'], $planSupportTypes, true))
                                            <option value="{{ $editForm['plan_type_3rd'] }}">{{ $editForm['plan_type_3rd'] }}</option>
                                        @endif
                                        @foreach($planSupportTypes as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">4차 계획일</label>
                                    <input type="date" wire:model="editForm.plan_4th"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">4차 계획 타입</label>
                                    <select wire:model="editForm.plan_type_4th"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                        <option value="">-</option>
                                        @if(filled($editForm['plan_type_4th'] ?? '') && ! in_array($editForm['plan_type_4th'], $planSupportTypes, true))
                                            <option value="{{ $editForm['plan_type_4th'] }}">{{ $editForm['plan_type_4th'] }}</option>
                                        @endif
                                        @foreach($planSupportTypes as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>
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
                <x-admin.modal-header :title="$teacherModalEditMode ? '교사정보 수정하기' : 'TR 교사정보'">
                    <x-slot:actions>
                        @if(! $teacherModalEditMode && ! ($teacherDetailInfo['is_retired'] ?? false))
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
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg space-y-4">
                            <p class="text-sm text-red-800 font-medium">정말 이 교사를 퇴직 처리하시겠습니까? 이 작업은 되돌릴 수 없습니다.</p>
                            @include('partials.admin.teacher-retire-recommendation-fields')
                            <div class="flex gap-2">
                                <button type="button"
                                        wire:click="retireTeacher"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-70 cursor-not-allowed"
                                        class="px-3 py-1.5 text-xs text-white bg-red-600 rounded hover:bg-red-700 cursor-pointer">
                                    <span wire:loading.remove wire:target="retireTeacher">퇴직 확인</span>
                                    <span wire:loading wire:target="retireTeacher">처리 중...</span>
                                </button>
                                <button type="button"
                                        wire:click="cancelRetireTeacher"
                                        class="px-3 py-1.5 text-xs text-gray-600 border border-gray-300 rounded hover:bg-gray-50 cursor-pointer">
                                    취소
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- 수정 모드 (연락처「교사정보 수정하기」와 동일 필드 구성) --}}
                    @if($teacherModalEditMode)
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">기관명</label>
                                <div class="rounded-lg border border-blue-200 bg-blue-50/60 px-3 py-2.5 text-sm font-medium text-gray-900">
                                    <span class="text-blue-700">[{{ $teacherDetailInfo['sk_code'] ?? '-' }}]</span>
                                    {{ $teacherDetailInfo['school_name'] ?? '-' }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" wire:model="teacherProfileForm.name"
                                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">직급</label>
                                    <select wire:model="teacherProfileForm.position"
                                            class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                        <option value="">선택</option>
                                        <option value="원장">원장</option>
                                        <option value="교장">교장</option>
                                        <option value="부원장">부원장</option>
                                        <option value="교사">교사</option>
                                        <option value="행정">행정</option>
                                        <option value="교수 부장">교수 부장</option>
                                        <option value="교감">교감</option>
                                        <option value="기타">기타</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">eMail</label>
                                    <input type="email" wire:model="teacherProfileForm.email"
                                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                    <input type="text" wire:model="teacherProfileForm.phone"
                                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">GrapeSEED Essentials</label>
                                    <input type="date" wire:model="teacherProfileForm.gs_essentials"
                                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">LittleSEED Essentials</label>
                                    <input type="date" wire:model="teacherProfileForm.ls_essentials"
                                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                </div>
                            </div>

                            <div class="border-t border-b border-gray-100 py-4 space-y-4">
                                <div class="grid grid-cols-[110px_1fr] items-center gap-3">
                                    <label class="text-sm font-medium text-gray-700">근무 형태</label>
                                    <div class="flex items-center gap-6 text-sm">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio"
                                                   wire:model="teacherProfileForm.employment_type"
                                                   value="full_time"
                                                   class="w-4 h-4 text-mochi-header border-gray-300 focus:ring-mochi-header">
                                            <span class="text-gray-700">Full Time</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio"
                                                   wire:model="teacherProfileForm.employment_type"
                                                   value="part_time"
                                                   class="w-4 h-4 text-mochi-header border-gray-300 focus:ring-mochi-header">
                                            <span class="text-gray-700">Part Time</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio"
                                                   wire:model="teacherProfileForm.employment_type"
                                                   value="unspecified"
                                                   class="w-4 h-4 text-gray-500 border-gray-300 focus:ring-gray-400">
                                            <span class="text-gray-500">미지정</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="grid grid-cols-[110px_1fr] items-center gap-3">
                                    <label class="text-sm font-medium text-gray-700">수업참여</label>
                                    <div class="flex items-center gap-6 text-sm">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio"
                                                   wire:model="teacherProfileForm.class_participation"
                                                   value="in"
                                                   class="w-4 h-4 text-mochi-header border-gray-300 focus:ring-mochi-header">
                                            <span class="text-gray-700">수업(O)</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio"
                                                   wire:model="teacherProfileForm.class_participation"
                                                   value="out"
                                                   class="w-4 h-4 text-mochi-header border-gray-300 focus:ring-mochi-header">
                                            <span class="text-gray-700">수업(X)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea wire:model="teacherProfileForm.description" rows="4"
                                          class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header resize-none"></textarea>
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
                                        <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">직급</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['position'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">이메일</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['email'] ?? '-' }}</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">GrapeSEED 이수</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['gs_essentials'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">전화</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['phone'] ?? '-' }}</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">LittleSEED 이수</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['ls_essentials'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 Coach</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['tr'] ?? '-' }}</td>
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
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">근무 형태</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['employment_type_label'] ?? '미지정' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">CO</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['co'] ?? '-' }}</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관</th>
                                        <td class="px-3 py-2 {{ ! empty($teacherDetailInfo['is_terminated']) ? 'text-red-700 font-medium' : 'text-gray-900' }}">
                                            {{ $teacherDetailInfo['school_name'] ?? '-' }} ({{ $teacherDetailInfo['sk_code'] }})
                                        </td>
                                    </tr>
                                    @if($teacherDetailInfo['description'])
                                        <tr>
                                            <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">비고</th>
                                            <td colspan="3" class="px-3 py-2 text-gray-900 text-left whitespace-normal break-words">{{ $teacherDetailInfo['description'] }}</td>
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
                        <div class="coach-teacher-support-create-hidden">
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
                                    @elseif($pillAction === 'visit')
                                        <button type="button"
                                                wire:click.stop="openVisitModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>
                <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-2">
                    @if($teacherModalEditMode)
                        <button type="button"
                                wire:click="$set('teacherModalEditMode', false)"
                                class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 cursor-pointer">
                            취소
                        </button>
                        <button type="button"
                                wire:click="saveTeacherProfile"
                                class="cursor-pointer rounded-lg bg-mochi-header px-4 py-2 text-sm text-white hover:bg-mochi-header/90">
                            저장하기
                        </button>
                    @else
                        <button type="button"
                                wire:click="closeTeacherModal"
                                class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 cursor-pointer">
                            닫기
                        </button>
                    @endif
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
    @include('components.coach.visit-support-modal', ['visitConfig' => $visitConfig])

    <x-coach.teacher-support-history-detail-modal
        :show="$showTeacherSupportHistoryDetailModal"
        :detail="$selectedTeacherSupportHistoryDetail"
    />
</div>
