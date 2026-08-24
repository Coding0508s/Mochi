<div class="mochi-page">
    @if(session('error'))
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <h2 class="text-base font-semibold text-mochi-header">Coach Team 지원 KPI</h2>
            <span class="text-gray-300">|</span>
            <p class="text-xs text-gray-500">{{ $yearLabel }} · 총 {{ $teamTotal }}회 · Coach {{ $filteredCoachCount }}명</p>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <select wire:model.live="filterYear"
                        class="py-1.5 px-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    <option value="">전체 (최근 4년)</option>
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}">{{ $y }}년</option>
                    @endfor
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
    </div>

    <div class="mochi-filter-card" x-data="{ showCriteria: false }">
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700"
                  aria-label="팀 총 지원 횟수">
                총 지원 횟수
                <span class="font-semibold tabular-nums">{{ $teamTotal }}</span>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700"
                  aria-label="담당 Coach 수">
                Coach
                <span class="font-semibold tabular-nums">{{ $filteredCoachCount }}</span>
            </span>

            <button type="button"
                    class="text-xs text-gray-500 underline hover:text-mochi-header"
                    @click="showCriteria = !showCriteria"
                    :aria-expanded="showCriteria.toString()">
                집계 기준
            </button>

            <input type="search"
                   wire:model.live.debounce.300ms="searchCoach"
                   placeholder="Coach 검색"
                   class="ml-auto py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header min-w-[12rem]"
                   aria-label="Coach 이름 검색">
        </div>

        <p x-show="showCriteria"
           x-cloak
           class="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
            기관지원은 전화·대면·화상(완료)만, 교사지원은 보고서 완료 건만 집계합니다.
            On-Site 행에는 LS On-Site &amp; LVA를 합산합니다.
            연도 선택 시 해당 연 1월부터 다음 연 3월까지 집계합니다.
            「전체」는 최근 4년의 달력 1~12월 합산이며 다음 연 1~3월 열은 표시하지 않습니다.
            셀을 클릭하면 해당 내역 목록을 볼 수 있습니다.
        </p>
    </div>

    <div class="space-y-3">
        @forelse($activeCoachRows as $coachRow)
            @include('partials.coach.team-kpi-coach-accordion', [
                'coachRow' => $coachRow,
                'matrixRows' => $matrixRows,
                'periodColumns' => $periodColumns,
            ])
        @empty
            @if($zeroCoachRows->isEmpty())
                <div class="mochi-table-card px-4 py-12 text-center text-gray-400">
                    조건에 맞는 지원 내역이 없습니다.
                </div>
            @endif
        @endforelse

        @if($zeroCoachRows->isNotEmpty())
            <div x-data="{ open: false }" class="mochi-table-card overflow-hidden">
                <button type="button"
                        class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50/80"
                        @click="open = !open"
                        :aria-expanded="open.toString()">
                    <span class="font-medium text-gray-600">지원 없음 {{ $zeroCoachRows->count() }}명</span>
                    <span class="text-sm text-gray-400">총 0회</span>
                    <span class="ml-auto text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </button>

                <div x-show="open" x-cloak class="space-y-3 border-t border-gray-100 bg-gray-50/50 p-3">
                    @foreach($zeroCoachRows as $coachRow)
                        @include('partials.coach.team-kpi-coach-accordion', [
                            'coachRow' => $coachRow,
                            'matrixRows' => $matrixRows,
                            'periodColumns' => $periodColumns,
                        ])
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if($showListModal)
        <div class="mochi-modal-overlay" wire:click.self="closeListModal">
            <div class="mochi-modal-shell max-w-4xl max-h-[min(90vh,calc(100dvh-2rem))] min-h-0 flex flex-col" @click.stop>
                <x-admin.modal-header
                    :title="$listModalCoach.' — '.$listModalRowLabel"
                    :subtitle="($listModalPeriodLabel !== '' ? $listModalPeriodLabel : $yearLabel).' · '.count($listModalItems).'건'"
                    close-action="closeListModal"
                >
                    <x-slot:actions>
                        <a href="{{ $this->teacherSupportUrl($listModalCoach) }}"
                           class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                            교사 지원 현황
                        </a>
                    </x-slot:actions>
                </x-admin.modal-header>

                <div class="mochi-modal-body-scroll px-6 py-4">
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-sm whitespace-nowrap">
                            <thead class="mochi-table-head text-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left">날짜</th>
                                <th class="px-3 py-2 text-left">대상</th>
                                <th class="px-3 py-2 text-left">기관</th>
                                <th class="px-3 py-2 text-left">유형</th>
                                <th class="px-3 py-2 text-left">상태</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($listModalItems as $item)
                                <tr wire:key="kpi-detail-{{ $item['detail_key'] }}"
                                    class="mochi-table-row-hover border-t border-gray-50 cursor-pointer"
                                    wire:click="openDetailFromList(@js($item['detail_key']))">
                                    <td class="px-3 py-2 tabular-nums">{{ $item['date'] }}</td>
                                    <td class="px-3 py-2 font-medium text-mochi-header underline">{{ $item['subject'] }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $item['institution'] !== '' ? $item['institution'] : '—' }}</td>
                                    <td class="px-3 py-2">{{ $item['type_label'] }}</td>
                                    <td class="px-3 py-2">{{ $item['status'] !== '' ? $item['status'] : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-400">해당 내역이 없습니다.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">행을 클릭하면 상세 내용을 볼 수 있습니다.</p>
                </div>
            </div>
        </div>
    @endif

    <x-coach.teacher-support-history-detail-modal
        :show="$showDetailModal"
        :detail="$selectedDetail"
    />
</div>
