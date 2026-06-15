<div class="mochi-page">
    @if($calendarFlashMessage)
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm" data-mochi-flash-dismiss="3000" role="status">
            {{ $calendarFlashMessage }}
        </div>
    @elseif(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm" data-mochi-flash-dismiss="3000" role="status">
            {{ session('success') }}
        </div>
    @endif

    @php
        $eventClassFor = function ($schedule) use ($viewMode): string {
            $typeClasses = [
                'meeting' => 'mochi-calendar-event--meeting',
                'task' => 'mochi-calendar-event--task',
                'personal' => 'mochi-calendar-event--personal',
                'etc' => 'mochi-calendar-event--etc',
            ][$schedule->type] ?? 'mochi-calendar-event--etc';

            $ownedClass = $schedule->isOwnedHighlightFor(auth()->id(), $viewMode)
                ? 'mochi-calendar-event--owned-by-me'
                : '';

            return trim($typeClasses.' '.($schedule->status === 'cancelled' ? 'mochi-calendar-event--cancelled' : '').' '.($schedule->status === 'done' ? 'mochi-calendar-event--done' : '').' '.$ownedClass);
        };
    @endphp

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-base font-semibold text-mochi-header">일정 관리</h2>
            <span class="text-gray-300">|</span>
            <button wire:click="previousMonth" class="py-1.5 px-3 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">이전 달</button>
            <button wire:click="goToday" class="py-1.5 px-3 text-sm border border-blue-200 text-blue-700 rounded-lg hover:bg-blue-50">이번 달</button>
            <button wire:click="nextMonth" class="py-1.5 px-3 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">다음 달</button>
            <div class="text-lg font-bold text-gray-900">{{ $monthLabel }}</div>

            <div class="w-full lg:w-auto lg:ml-auto flex flex-wrap items-center gap-2">
                {{-- 내 일정 / 팀 일정 토글 --}}
                <div class="mochi-toggle-group">
                    <button type="button" wire:click="$set('viewMode', 'mine')"
                            class="mochi-toggle-btn {{ $viewMode === 'mine' ? 'mochi-toggle-btn--active' : '' }}">
                        내 일정
                    </button>
                    <button type="button" wire:click="$set('viewMode', 'team')"
                            class="mochi-toggle-btn {{ $viewMode === 'team' ? 'mochi-toggle-btn--active' : '' }}">
                        팀 일정
                    </button>
                </div>

                {{-- 캘린더 / 리스트 뷰 토글 --}}
                <div class="mochi-toggle-group">
                    <button type="button" wire:click="$set('displayMode', 'calendar')" title="캘린더 보기"
                            class="mochi-toggle-btn {{ $displayMode === 'calendar' ? 'mochi-toggle-btn--active' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </button>
                    <button type="button" wire:click="$set('displayMode', 'list')" title="리스트 보기"
                            class="mochi-toggle-btn {{ $displayMode === 'list' ? 'mochi-toggle-btn--active' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <line x1="3" y1="6" x2="3.01" y2="6"></line>
                            <line x1="3" y1="12" x2="3.01" y2="12"></line>
                            <line x1="3" y1="18" x2="3.01" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <select wire:model.live="filterType" class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 max-lg:flex-1">
                    <option value="">전체 유형</option>
                    <option value="meeting">미팅</option>
                    <option value="task">업무</option>
                    <option value="personal">개인</option>
                    <option value="etc">기타</option>
                </select>

                <select wire:model.live="filterStatus" class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 max-lg:flex-1">
                    <option value="">전체 상태</option>
                    <option value="planned">예정</option>
                    <option value="done">완료</option>
                    <option value="cancelled">취소</option>
                </select>

                @if($viewMode === 'team' && auth()->user()?->hasPlatformWideViewAccess())
                    <select wire:model.live="userFilter" class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 max-lg:flex-1">
                        <option value="">전체 팀원</option>
                        @foreach($teamUsers as $teamUser)
                            <option value="{{ $teamUser->id }}">{{ $teamUser->name ?: $teamUser->email }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>
    </div>

    @if($viewMode === 'team')
        <p class="mb-2 flex items-center gap-2 text-xs text-gray-500">
            <span class="inline-block h-3 w-3 rounded-sm border-l-[3px] border-l-green-600 bg-green-50" aria-hidden="true"></span>
            초록색 = 나와 관련된 팀 일정
        </p>
    @endif

    @if($displayMode === 'calendar')
        <div class="mochi-calendar-card">
            <div class="grid grid-cols-7">
                @foreach(['일', '월', '화', '수', '목', '금', '토'] as $weekday)
                    <div class="mochi-calendar-weekday">{{ $weekday }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7">
                @foreach($calendar as $day)
                    @php
                        $dateSchedules = $schedulesByDate->get($day['date'], collect());
                    @endphp
                    <div wire:key="schedule-day-{{ $day['date'] }}"
                         wire:click="openCreateModal('{{ $day['date'] }}')"
                         class="mochi-calendar-day {{ $day['currentMonth'] ? '' : 'mochi-calendar-day--muted' }} {{ $day['today'] ? 'mochi-calendar-day--today' : '' }}">
                        <div class="flex items-center justify-between">
                            <span class="mochi-calendar-date {{ $day['today'] ? 'mochi-calendar-date--today' : '' }}">
                                {{ $day['day'] }}
                            </span>
                        </div>

                        <div class="mt-2 space-y-1">
                            @foreach($dateSchedules->take(4) as $schedule)
                                <button type="button"
                                        wire:click.stop="openEditModal({{ $schedule->id }})"
                                        class="mochi-calendar-event {{ $eventClassFor($schedule) }}">
                                    @unless($schedule->is_all_day)
                                        <span class="font-mono">{{ $schedule->starts_at->format('H:i') }}</span>
                                    @endunless
                                    {{ $schedule->title }}
                                    @if($viewMode === 'team')
                                        <span class="text-[10px] opacity-75">· {{ $schedule->user?->name ?? 'User' }}</span>
                                    @endif
                                </button>
                            @endforeach

                            @if($dateSchedules->count() > 4)
                                <button type="button"
                                        wire:click.stop="openDayModal('{{ $day['date'] }}')"
                                        class="mochi-calendar-overflow text-left hover:text-blue-600">
                                    +{{ $dateSchedules->count() - 4 }}개 더 있음
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        @php
            $weekdayKo = ['Sun' => '일', 'Mon' => '월', 'Tue' => '화', 'Wed' => '수', 'Thu' => '목', 'Fri' => '금', 'Sat' => '토'];
            $groupedListSchedules = $listSchedules->groupBy(fn ($s) => $s->starts_at->format('Y-m-d'));
        @endphp
        <div class="mochi-calendar-card divide-y divide-gray-100">
            @forelse($groupedListSchedules as $groupDate => $dayList)
                @php
                    $groupCarbon = \Carbon\Carbon::parse($groupDate);
                    $relativeDayLabel = $groupCarbon->isToday() ? '오늘 · ' : ($groupCarbon->isTomorrow() ? '내일 · ' : ($groupCarbon->isYesterday() ? '어제 · ' : ''));
                    $dateLabel = $groupCarbon->format('Y년 n월 j일') . ' (' . ($weekdayKo[$groupCarbon->format('D')] ?? '') . ')';
                @endphp
                <div class="mochi-list-date-header">{{ $relativeDayLabel }}{{ $dateLabel }}</div>
                @foreach($dayList as $schedule)
                    <button type="button"
                            wire:click="openEditModal({{ $schedule->id }})"
                            class="mochi-schedule-list-row">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $schedule->title }}</p>
                                <p class="text-xs text-gray-500">
                                    @if($schedule->is_all_day)
                                        종일
                                    @else
                                        {{ $schedule->starts_at->format('H:i') }}{{ $schedule->ends_at ? ' ~ '.$schedule->ends_at->format('H:i') : '' }}
                                    @endif
                                    @if($viewMode === 'team')
                                        · {{ $schedule->user?->name ?? 'User' }}
                                    @endif
                                </p>
                            </div>
                            <span class="mochi-calendar-event {{ $eventClassFor($schedule) }} w-auto">
                                {{ ['meeting' => '미팅', 'task' => '업무', 'personal' => '개인', 'etc' => '기타'][$schedule->type] ?? '기타' }}
                                · {{ ['planned' => '예정', 'done' => '완료', 'cancelled' => '취소'][$schedule->status] ?? '예정' }}
                            </span>
                        </div>
                    </button>
                @endforeach
            @empty
                <div class="px-6 py-12 text-center text-sm text-gray-400">표시할 일정이 없습니다.</div>
            @endforelse
        </div>
    @endif

    @if($showDayModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" wire:click.self="closeDayModal">
            <div class="w-full max-w-xl rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $selectedDay }} 전체 일정</h3>
                    <button type="button" wire:click="closeDayModal"
                            class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="max-h-[70vh] space-y-2 overflow-y-auto px-6 py-5">
                    @forelse($daySchedules as $schedule)
                        <button type="button"
                                wire:click="openEditModal({{ $schedule->id }})"
                                class="mochi-schedule-list-row rounded-xl border border-gray-100">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $schedule->title }}</p>
                                    <p class="text-xs text-gray-500">
                                        @if($schedule->is_all_day)
                                            종일
                                        @else
                                            {{ $schedule->starts_at->format('H:i') }}{{ $schedule->ends_at ? ' ~ '.$schedule->ends_at->format('H:i') : '' }}
                                        @endif
                                        @if($viewMode === 'team')
                                            · {{ $schedule->user?->name ?? 'User' }}
                                        @endif
                                    </p>
                                </div>
                                <span class="mochi-calendar-event {{ $eventClassFor($schedule) }} w-auto">
                                    {{ ['planned' => '예정', 'done' => '완료', 'cancelled' => '취소'][$schedule->status] ?? '예정' }}
                                </span>
                            </div>
                        </button>
                    @empty
                        <p class="py-8 text-center text-sm text-gray-400">일정이 없습니다.</p>
                    @endforelse
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-6 py-4">
                    <button type="button"
                            wire:click="openCreateModal('{{ $selectedDay }}')"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        일정 추가
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showAddChoiceModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4" wire:click.self="closeAddChoiceModal">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl" wire:click.stop>
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">일정 추가</h3>
                        <p class="text-sm text-gray-500">{{ $pendingCreateDate }} · 추가 유형을 선택하세요.</p>
                    </div>
                    <button type="button" wire:click="closeAddChoiceModal"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                        ✕
                    </button>
                </div>
                <div class="space-y-2 px-6 py-5">
                    <button type="button"
                            wire:click="openPersonalScheduleCreate"
                            class="w-full rounded-xl border border-gray-200 px-4 py-3 text-left hover:border-blue-200 hover:bg-blue-50/40">
                        <p class="text-sm font-semibold text-gray-900">개인·팀 일정</p>
                        <p class="mt-1 text-xs text-gray-500">미팅, 업무, 개인 일정 등 일반 캘린더 일정</p>
                    </button>
                    <p class="pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">공용품 예약</p>
                    <button type="button"
                            wire:click="openSharedSupplyCreate('[출장 차량배차] 신청 및 예약')"
                            class="w-full rounded-xl border border-gray-200 px-4 py-3 text-left hover:border-indigo-200 hover:bg-indigo-50/40">
                        <p class="text-sm font-semibold text-gray-900">차량 배차</p>
                        <p class="mt-1 text-xs text-gray-500">출장 차량 예약 및 운행 기록</p>
                    </button>
                    <button type="button"
                            wire:click="openSharedSupplyCreate('[회의실] 신청 및 예약 (팀 회의)')"
                            class="w-full rounded-xl border border-gray-200 px-4 py-3 text-left hover:border-indigo-200 hover:bg-indigo-50/40">
                        <p class="text-sm font-semibold text-gray-900">회의실</p>
                        <p class="mt-1 text-xs text-gray-500">회의실 예약 (팀 회의·기타)</p>
                    </button>
                    <button type="button"
                            wire:click="openSharedSupplyCreate('[출장] 출장')"
                            class="w-full rounded-xl border border-gray-200 px-4 py-3 text-left hover:border-indigo-200 hover:bg-indigo-50/40">
                        <p class="text-sm font-semibold text-gray-900">출장</p>
                        <p class="mt-1 text-xs text-gray-500">출장·해외출장 등 업무 일정</p>
                    </button>
                    <button type="button"
                            wire:click="openSharedSupplyCreate('[휴가] 연차휴가')"
                            class="w-full rounded-xl border border-gray-200 px-4 py-3 text-left hover:border-indigo-200 hover:bg-indigo-50/40">
                        <p class="text-sm font-semibold text-gray-900">연차</p>
                        <p class="mt-1 text-xs text-gray-500">연차휴가 등 휴가 일정</p>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showFormModal)
        <div class="mochi-modal-overlay" wire:key="team-schedule-form-modal">
            <div class="mochi-modal-shell max-w-2xl max-h-[90vh] min-h-0 flex flex-col">
                <div class="shrink-0 flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $viewOnly ? '일정 상세' : ($editingScheduleId ? '일정 수정' : '일정 추가') }}</h3>
                        <p class="text-sm text-gray-500">{{ $viewOnly ? '다른 사람 일정은 보기만 가능합니다.' : '본인 일정은 직접 수정할 수 있고, 팀 공개 일정은 팀 일정 보기에서 공유됩니다.' }}</p>
                    </div>
                    <button type="button" wire:click="closeFormModal"
                            class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="save" class="flex min-h-0 flex-1 flex-col">
                    <div class="mochi-modal-body-scroll flex-1 space-y-4 px-6 py-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">제목 <span class="text-red-500">*</span></label>
                        <input type="text" wire:model.defer="title" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">날짜 <span class="text-red-500">*</span></label>
                            <input type="date" wire:model.defer="date" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                            @error('date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">시작 시간</label>
                            <x-ui.time-select wire:model.defer="startTime" :disabled="$isAllDay || $viewOnly" />
                            @error('startTime') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">종료 시간</label>
                            <x-ui.time-select wire:model.defer="endTime" :disabled="$isAllDay || $viewOnly" />
                            @error('endTime') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="isAllDay" @disabled($viewOnly) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:bg-gray-100">
                        종일 일정
                    </label>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">유형</label>
                            <select wire:model.defer="type" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                                <option value="meeting">미팅</option>
                                <option value="task">업무</option>
                                <option value="personal">개인</option>
                                <option value="etc">기타</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">공개 범위</label>
                            <select wire:model.defer="visibility" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                                <option value="private">비공개</option>
                                <option value="team">팀 공개</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">상태</label>
                            <select wire:model.defer="status" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                                <option value="planned">예정</option>
                                <option value="done">완료</option>
                                <option value="cancelled">취소</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">장소</label>
                        <input type="text" wire:model.defer="location" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                    </div>

                    @if(! $editingScheduleId)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">반복</label>
                            <select wire:model.defer="recurrenceRule" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                                <option value="">없음</option>
                                <option value="weekly">매주</option>
                                <option value="biweekly">격주</option>
                                <option value="monthly">매월</option>
                            </select>
                            @error('recurrenceRule') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @elseif($recurrenceRule !== '')
                        <p class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
                            반복 일정: {{ ['weekly' => '매주', 'biweekly' => '격주', 'monthly' => '매월'][$recurrenceRule] ?? '반복' }}
                        </p>
                    @endif

                    @if($showRecurrenceDeleteModal)
                        <div class="rounded-xl border border-red-100 bg-red-50 px-4 py-3">
                            <p class="mb-3 text-sm font-semibold text-red-800">반복 일정 삭제 범위를 선택해 주세요.</p>
                            <label class="mb-2 flex items-center gap-2 text-sm text-red-900">
                                <input type="radio" wire:model.live="recurrenceDeleteScope" value="single" class="text-red-600">
                                이 일정만 삭제
                            </label>
                            <label class="flex items-center gap-2 text-sm text-red-900">
                                <input type="radio" wire:model.live="recurrenceDeleteScope" value="all_following" class="text-red-600">
                                이 일정과 이후 반복 일정 모두 삭제
                            </label>
                            <div class="mt-3 flex justify-end gap-2">
                                <button type="button" wire:click="cancelRecurringDelete" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-white">취소</button>
                                <button type="button" wire:click="confirmRecurringDelete" class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700">삭제</button>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">설명</label>
                        <textarea wire:model.defer="description" rows="4" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100"></textarea>
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    </div>

                    <div class="shrink-0 flex items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <div>
                            @if($editingScheduleId && ! $viewOnly)
                                <button type="button" wire:click="delete" wire:confirm="이 일정을 삭제할까요?" class="rounded-lg border border-red-200 px-4 py-2 text-sm text-red-600 hover:bg-red-50">삭제</button>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="closeFormModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">취소</button>
                            @unless($viewOnly)
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">저장</button>
                            @endunless
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
