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
        @if(auth()->user()->hasFullAccess() && $unreadCount > 0)
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
                @unless(auth()->user()->hasFullAccess())
                    <div class="mt-1 text-xs font-medium text-gray-600">
                        최근 24시간 수신 {{ $recent24hCount }}건 · 목록만 조회 가능
                    </div>
                @endunless
            </div>
            @if(auth()->user()->hasFullAccess())
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    class="shrink-0 text-xs font-medium text-[#2b78c5] hover:underline"
                >
                    모두 읽음
                </button>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto">
            @forelse($recentRows as $row)
                @php
                    $href = str_starts_with($row['sk_code'], 'LEAD-')
                        ? route('potential-institutions.view')
                        : route('institutions.index');
                @endphp
                @if(auth()->user()->hasFullAccess())
                    <a
                        href="{{ $href }}"
                        wire:key="inbound-row-admin-{{ $row['id'] }}"
                        class="block border-b border-gray-50 px-3 py-2.5 text-left transition hover:bg-gray-50 {{ ($row['is_unread'] ?? false) ? 'bg-sky-50/80' : '' }}"
                    >
                        @include('livewire.partials.inbound-notification-row-body', ['row' => $row])
                    </a>
                @else
                    <div
                        wire:key="inbound-row-view-{{ $row['id'] }}"
                        class="border-b border-gray-50 px-3 py-2.5 text-left"
                    >
                        @include('livewire.partials.inbound-notification-row-body', ['row' => $row])
                    </div>
                @endif
            @empty
                <p class="px-3 py-6 text-center text-sm text-gray-500">E-Ordering에서 보낸 기관 정보가 없습니다.</p>
            @endforelse
        </div>
    </div>
</div>
@endauth
</div>
