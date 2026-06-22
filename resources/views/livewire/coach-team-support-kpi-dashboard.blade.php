<div class="mochi-page">
    @php
        $kpiToggleLabels = \App\Support\TeacherSupportKpiCalculator::visibleToggleLabels();
        $kpiRoundKeys = \App\Support\TeacherSupportKpiCalculator::roundKpiKeys();
        $totalSupportCount = \App\Support\TeacherSupportKpiCalculator::totalSupportCount($teamKpis);
        $planMonthRound = $filterRound !== '' ? $filterRound : '1';
        $planMonthRoundLabel = $planMonthRound.'차';
        $tableColumnCount = 2 + count($kpiRoundKeys);
    @endphp

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">Coach Team 지원 KPI</h2>
            <span class="text-gray-300">|</span>
            <p class="text-xs text-gray-500">지원 완료 차수 기준 · 팀 전체 (1~4차)</p>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <select wire:model.live="filterYear"
                        class="py-1.5 px-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}">{{ $y }}년</option>
                    @endfor
                </select>
                <a href="{{ \App\Support\TeamMenuContext::route('coach.teacher-support.index') }}"
                   class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600">
                    교사 지원 현황
                </a>
            </div>
        </div>
    </div>

    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <div class="mochi-toggle-group flex-wrap">
                <span class="mochi-toggle-btn cursor-default pointer-events-none text-gray-700"
                      aria-label="기관 수">
                    기관 수
                    <span class="font-semibold">{{ $teamKpis['institution_count'] ?? 0 }}</span>
                </span>
                <span class="mochi-toggle-btn cursor-default pointer-events-none text-gray-700"
                      aria-label="교사 수">
                    교사 수
                    <span class="font-semibold">{{ $teamKpis['teacher_count'] ?? 0 }}</span>
                </span>
                <span class="mochi-toggle-btn cursor-default pointer-events-none text-green-800"
                      aria-label="완료 교사 수" title="선택 연도에 1~4차 중 하나라도 완료한 교사 수">
                    완료 교사
                    <span class="font-semibold">{{ $teamKpis['any_completed'] ?? 0 }}</span>
                </span>
                @foreach($kpiToggleLabels as $kpiKey => $kpiLabel)
                    <button type="button"
                            wire:click="setHighlightKpi('{{ $kpiKey }}')"
                            aria-pressed="{{ $highlightKpi === $kpiKey ? 'true' : 'false' }}"
                            class="mochi-toggle-btn
                                {{ $highlightKpi === $kpiKey
                                    ? ($kpiKey === 'completed'
                                        ? 'mochi-toggle-btn--active-success'
                                        : ($kpiKey === 'unsupported'
                                            ? 'mochi-toggle-btn--active-danger'
                                            : 'mochi-toggle-btn--active'))
                                    : '' }}">
                        {{ $kpiLabel }}
                        <span class="font-semibold">{{ $teamKpis[$kpiKey] ?? 0 }}</span>
                    </button>
                @endforeach
                <span class="mochi-toggle-btn cursor-default pointer-events-none text-gray-700"
                      aria-label="총 지원 횟수">
                    총 지원 횟수
                    <span class="font-semibold">{{ $totalSupportCount }}</span>
                </span>
            </div>

            <select wire:model.live="filterRound"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="">차수 전체</option>
                @foreach(config('coach_teacher_support.kpi_rounds', []) as $round)
                    <option value="{{ $round['filter_round'] }}">{{ $round['filter_round'] }}차 계획</option>
                @endforeach
            </select>

            <select wire:model.live="filterMonth"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="">전체 월</option>
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}">{{ $m }}월 ({{ $planMonthRoundLabel }} 계획)</option>
                @endfor
            </select>

            @if($filterMonth !== '' || $filterRound !== '')
                <button type="button" wire:click="resetFilters"
                        class="py-2 px-3 text-sm text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                    초기화
                </button>
            @endif
        </div>

        @if($filterMonth !== '' || $filterRound !== '')
            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 text-xs">
                <span class="text-gray-400">적용 필터</span>
                @if($filterRound !== '')
                    <span class="inline-flex items-center gap-1 rounded-full border border-mochi-header/20 bg-mochi-header/10 px-2.5 py-1 font-medium text-mochi-header">
                        {{ $filterRound }}차 계획
                        <button type="button" wire:click="clearRoundFilter" class="ml-0.5 text-mochi-header/60 hover:text-mochi-header" aria-label="차수 필터 해제">×</button>
                    </span>
                @endif
                @if($filterMonth !== '')
                    <span class="inline-flex items-center gap-1 rounded-full border border-mochi-header/20 bg-mochi-header/10 px-2.5 py-1 font-medium text-mochi-header">
                        {{ $planMonthRoundLabel }} 계획월: {{ $filterYear }}년 {{ $filterMonth }}월
                        <button type="button" wire:click="clearMonthFilter" class="ml-0.5 text-mochi-header/60 hover:text-mochi-header" aria-label="월 필터 해제">×</button>
                    </span>
                @endif
            </div>
        @endif

        <p class="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
            KPI는 선택 연도의 지원 완료일 기준입니다.
            월 필터는 선택한 차수의 지원 계획월입니다(차수 미선택 시 1차). 담당 Coach 이름을 클릭하면 교사별 계획·완료 일정을 모달로 볼 수 있습니다.
        </p>
    </div>

    <div class="mochi-table-card overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="mochi-table-head">
            <tr class="text-gray-700">
                <th class="px-3 py-2 text-left">담당 Coach</th>
                <th class="px-3 py-2 text-right">교사 수</th>
                @foreach($kpiRoundKeys as $roundKey)
                    <th class="px-3 py-2 text-right">{{ $kpiToggleLabels[$roundKey] ?? $roundKey }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @forelse($coachRows as $row)
                <tr wire:key="coach-kpi-{{ $row['coach'] }}" class="mochi-table-row-hover">
                    <td class="px-3 py-2">
                        <button type="button"
                                wire:click="openCoachScheduleModal(@js($row['coach']))"
                                class="font-medium text-mochi-header underline hover:text-mochi-header/80 cursor-pointer text-left">
                            {{ $row['coach'] }}
                        </button>
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ $row['teacher_count'] }}</td>
                    @foreach($kpiRoundKeys as $roundKey)
                        <td class="px-3 py-2 text-right tabular-nums {{ $highlightKpi === $roundKey ? 'font-semibold text-mochi-header' : '' }}">{{ $row[$roundKey] ?? 0 }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $tableColumnCount }}" class="px-4 py-12 text-center text-gray-400">
                        조건에 맞는 담당 Coach가 없습니다.
                    </td>
                </tr>
            @endforelse
            </tbody>
            @if($coachRows->isNotEmpty())
                <tfoot>
                <tr class="border-t border-gray-200 bg-gray-50/80 font-semibold text-gray-800">
                    <td class="px-3 py-2">팀 합계</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $coachRows->sum('teacher_count') }}</td>
                    @foreach($kpiRoundKeys as $roundKey)
                        <td class="px-3 py-2 text-right tabular-nums">{{ $teamKpis[$roundKey] ?? 0 }}</td>
                    @endforeach
                </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if($showCoachScheduleModal)
        <div class="mochi-modal-overlay" wire:click.self="closeCoachScheduleModal">
            <div class="mochi-modal-shell max-w-5xl max-h-[min(90vh,calc(100dvh-2rem))] min-h-0 flex flex-col" @click.stop>
                <x-admin.modal-header
                    :title="$coachScheduleModalCoach.' — 지원 계획 일정'"
                    :subtitle="$filterYear.'년 · 교사 '.count($coachScheduleRows).'명'"
                    close-action="closeCoachScheduleModal"
                >
                    <x-slot:actions>
                        <a href="{{ $this->teacherSupportUrl($coachScheduleModalCoach) }}"
                           class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                            교사 지원 현황
                        </a>
                    </x-slot:actions>
                </x-admin.modal-header>

                <div class="mochi-modal-body-scroll px-6 py-4 space-y-4">
                    @php
                        $modalKpiLabels = \App\Support\TeacherSupportKpiCalculator::visibleToggleLabels();
                        $modalTotalSupportCount = \App\Support\TeacherSupportKpiCalculator::totalSupportCount($coachScheduleKpis);
                    @endphp

                    <div class="space-y-3 rounded-lg border border-gray-200 bg-gray-50/80 p-4">
                        <p class="text-xs font-medium text-gray-500">{{ $filterYear }}년 지원 완료 차수 집계</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($modalKpiLabels as $kpiKey => $kpiLabel)
                                <span class="inline-flex items-center gap-1 rounded-full border border-mochi-header/20 bg-white px-2.5 py-1 text-xs font-medium text-mochi-header">
                                    {{ $kpiLabel }}
                                    <span class="tabular-nums font-semibold">{{ $coachScheduleKpis[$kpiKey] ?? 0 }}</span>
                                </span>
                            @endforeach
                            <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-700">
                                총 지원 횟수
                                <span class="tabular-nums font-semibold">{{ $modalTotalSupportCount }}</span>
                            </span>
                        </div>

                        @if(($coachScheduleSummary['teacher_count'] ?? 0) > 0)
                            <div class="border-t border-gray-200 pt-3">
                                <p class="mb-2 text-xs font-medium text-gray-500">계획 일정 집계 (모달 표시 기준)</p>
                                <div class="mb-3 flex flex-wrap gap-3 text-sm text-gray-800">
                                    <span>계획 교사 <strong class="tabular-nums">{{ $coachScheduleSummary['teacher_count'] }}</strong>명</span>
                                    <span>계획 차수 <strong class="tabular-nums">{{ $coachScheduleSummary['planned_round_count'] }}</strong>건</span>
                                    <span class="text-green-800">완료 <strong class="tabular-nums">{{ $coachScheduleSummary['completed_count'] }}</strong></span>
                                    <span class="text-amber-700">미완료 <strong class="tabular-nums">{{ $coachScheduleSummary['pending_count'] }}</strong></span>
                                </div>
                                <div class="overflow-x-auto rounded border border-gray-200 bg-white">
                                    <table class="w-full text-xs whitespace-nowrap">
                                        <thead class="mochi-table-head text-gray-700">
                                        <tr>
                                            <th class="px-2 py-1.5 text-left border-b">차수</th>
                                            <th class="px-2 py-1.5 text-right border-b">계획</th>
                                            <th class="px-2 py-1.5 text-right border-b">완료</th>
                                            <th class="px-2 py-1.5 text-right border-b">미완료</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($coachScheduleSummary['by_round'] ?? [] as $roundSummary)
                                            @if(($roundSummary['planned'] ?? 0) > 0)
                                                <tr class="border-b border-gray-100 last:border-0">
                                                    <td class="px-2 py-1.5 font-medium">{{ $roundSummary['label'] }}</td>
                                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ $roundSummary['planned'] }}</td>
                                                    <td class="px-2 py-1.5 text-right tabular-nums text-green-800">{{ $roundSummary['completed'] }}</td>
                                                    <td class="px-2 py-1.5 text-right tabular-nums text-amber-700">{{ $roundSummary['pending'] }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        </tbody>
                                        <tfoot>
                                        <tr class="bg-gray-50 font-semibold text-gray-800">
                                            <td class="px-2 py-1.5">합계</td>
                                            <td class="px-2 py-1.5 text-right tabular-nums">{{ $coachScheduleSummary['planned_round_count'] }}</td>
                                            <td class="px-2 py-1.5 text-right tabular-nums">{{ $coachScheduleSummary['completed_count'] }}</td>
                                            <td class="px-2 py-1.5 text-right tabular-nums">{{ $coachScheduleSummary['pending_count'] }}</td>
                                        </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    @forelse($coachScheduleRows as $schedule)
                        <section wire:key="coach-schedule-{{ $schedule['teacher_id'] }}"
                                 class="rounded-lg border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                                <p class="text-sm font-semibold text-gray-900">{{ $schedule['teacher_name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $schedule['institution_name'] }}</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs whitespace-nowrap">
                                    <thead class="mochi-table-head text-gray-700">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left border-b">차수</th>
                                        <th class="px-2 py-1.5 text-left border-b">계획일</th>
                                        <th class="px-2 py-1.5 text-left border-b">계획 타입</th>
                                        <th class="px-2 py-1.5 text-left border-b">완료일</th>
                                        <th class="px-2 py-1.5 text-left border-b">완료 타입</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($schedule['rounds'] as $round)
                                        <tr class="border-b border-gray-100 last:border-0">
                                            <td class="px-2 py-1.5 font-medium text-gray-700">{{ $round['label'] }}</td>
                                            <td class="px-2 py-1.5">{{ $round['plan_date'] !== '' ? $round['plan_date'] : '—' }}</td>
                                            <td class="px-2 py-1.5">{{ $round['plan_type'] !== '' ? $round['plan_type'] : '—' }}</td>
                                            <td class="px-2 py-1.5 {{ $round['completed_date'] !== '—' ? 'text-green-800 bg-green-50/80' : '' }}">{{ $round['completed_date'] }}</td>
                                            <td class="px-2 py-1.5">{{ $round['completed_type'] !== '' ? $round['completed_type'] : '—' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @empty
                        <p class="py-8 text-center text-sm text-gray-400">계획된 지원 일정이 없습니다.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
