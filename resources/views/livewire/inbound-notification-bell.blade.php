<div>
@auth
<div
    class="relative flex shrink-0 items-center"
    wire:poll.30s="loadCounters"
    x-data="{ open: false }"
    @click.outside="open = false"
>
    <button
        type="button"
        class="mochi-topbar-action mochi-topbar-bell relative inline-flex items-center justify-center"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="true"
        aria-label="외부 시스템 연동 알림 열기"
        @click="open = !open"
    >
        {{-- Lucide 스타일 벨: 종 몸통 + 하단 클래퍼 --}}
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="size-[22px] shrink-0 opacity-[0.96]"
            aria-hidden="true"
        >
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </svg>
        @if($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold leading-none text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        class="absolute right-0 top-full z-50 mt-1 w-[min(22rem,calc(100vw-2rem))] rounded-md border border-gray-200 bg-white py-2 shadow-lg"
        role="menu"
    >
        <div class="flex items-start justify-between gap-2 border-b border-gray-100 px-3 pb-2">
            <div>
                <div class="text-sm font-semibold text-gray-900">외부 시스템 연동</div>
                <div class="mt-0.5 text-xs leading-snug text-gray-500">
                    E-Ordering에서 보낸 기관 정보가 등록되면 여기에 표시됩니다.
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    class="text-xs font-medium text-mochi-header hover:underline"
                >
                    모두 읽음
                </button>
                @if(count($recentRows) > 0)
                    <button
                        type="button"
                        wire:click="deleteAllLogs"
                        wire:confirm="알림 목록을 모두 삭제할까요? 되돌릴 수 없습니다."
                        class="text-xs font-medium text-gray-400 hover:text-red-500 hover:underline"
                    >
                        전체 삭제
                    </button>
                @endif
            </div>
        </div>

        <div class="max-h-80 overflow-y-auto">
            @forelse($recentRows as $row)
                @php
                    $href = str_starts_with($row['sk_code'], 'LEAD-')
                        ? route('potential-institutions.view')
                        : route('institutions.index');
                @endphp
                <div
                    wire:key="inbound-row-{{ $row['id'] }}"
                    class="group relative border-b border-gray-50 {{ ($row['is_unread'] ?? false) ? 'bg-sky-50/80' : '' }}"
                >
                    <a
                        href="{{ $href }}"
                        class="block px-3 py-2.5 pr-8 text-left transition hover:bg-gray-50"
                    >
                        @include('livewire.partials.inbound-notification-row-body', ['row' => $row])
                    </a>
                    <button
                        type="button"
                        wire:click.stop="deleteLog({{ $row['id'] }})"
                        title="이 알림 삭제"
                        class="absolute right-2 top-2.5 hidden rounded p-0.5 text-gray-300 hover:bg-red-50 hover:text-red-400 group-hover:flex"
                        aria-label="알림 삭제"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @empty
                <p class="px-3 py-6 text-center text-sm text-gray-500">E-Ordering에서 보낸 기관 정보가 없습니다.</p>
            @endforelse
        </div>
    </div>
</div>
@endauth
</div>
