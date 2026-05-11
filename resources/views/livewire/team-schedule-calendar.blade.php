<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-base font-semibold text-[#2b78c5]">일정 관리</h2>
            <span class="text-gray-300">|</span>
            <button wire:click="previousMonth" class="py-1.5 px-3 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">이전 달</button>
            <button wire:click="goToday" class="py-1.5 px-3 text-sm border border-blue-200 text-blue-700 rounded-lg hover:bg-blue-50">이번 달</button>
            <button wire:click="nextMonth" class="py-1.5 px-3 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">다음 달</button>
            <div class="text-lg font-bold text-gray-900">{{ $monthLabel }}</div>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <select wire:model.live="viewMode" class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="mine">내 일정</option>
                    <option value="team">팀 일정</option>
                </select>

                @if($viewMode === 'team' && auth()->user()?->hasFullAccess())
                    <select wire:model.live="userFilter" class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">전체 팀원</option>
                        @foreach($teamUsers as $teamUser)
                            <option value="{{ $teamUser->id }}">{{ $teamUser->name ?: $teamUser->email }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>
    </div>

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
                            @php
                                $typeClasses = [
                                    'meeting' => 'mochi-calendar-event--meeting',
                                    'task' => 'mochi-calendar-event--task',
                                    'personal' => 'mochi-calendar-event--personal',
                                    'etc' => 'mochi-calendar-event--etc',
                                ][$schedule->type] ?? 'mochi-calendar-event--etc';
                            @endphp
                            <button type="button"
                                    wire:click.stop="openEditModal({{ $schedule->id }})"
                                    class="mochi-calendar-event {{ $typeClasses }} {{ $schedule->status === 'cancelled' ? 'mochi-calendar-event--cancelled' : '' }}">
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
                            <div class="mochi-calendar-overflow">+{{ $dateSchedules->count() - 4 }}개 더 있음</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $editingScheduleId ? '일정 수정' : '일정 추가' }}</h3>
                        <p class="text-sm text-gray-500">본인 일정은 직접 수정할 수 있고, 팀 공개 일정은 팀 일정 보기에서 공유됩니다.</p>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="text-gray-400 hover:text-gray-600">닫기</button>
                </div>

                <form wire:submit.prevent="save" class="space-y-4 px-6 py-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">제목 <span class="text-red-500">*</span></label>
                        <input type="text" wire:model.defer="title" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">날짜 <span class="text-red-500">*</span></label>
                            <input type="date" wire:model.defer="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">시작 시간</label>
                            <input type="time" wire:model.defer="startTime" @disabled($isAllDay) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                            @error('startTime') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">종료 시간</label>
                            <input type="time" wire:model.defer="endTime" @disabled($isAllDay) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                            @error('endTime') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="isAllDay" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        종일 일정
                    </label>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">유형</label>
                            <select wire:model.defer="type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="meeting">미팅</option>
                                <option value="task">업무</option>
                                <option value="personal">개인</option>
                                <option value="etc">기타</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">공개 범위</label>
                            <select wire:model.defer="visibility" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="private">비공개</option>
                                <option value="team">팀 공개</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">상태</label>
                            <select wire:model.defer="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="planned">예정</option>
                                <option value="done">완료</option>
                                <option value="cancelled">취소</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">장소</label>
                        <input type="text" wire:model.defer="location" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">설명</label>
                        <textarea wire:model.defer="description" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                        <div>
                            @if($editingScheduleId)
                                <button type="button" wire:click="delete" wire:confirm="이 일정을 삭제할까요?" class="rounded-lg border border-red-200 px-4 py-2 text-sm text-red-600 hover:bg-red-50">삭제</button>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="closeFormModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">취소</button>
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">저장</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
