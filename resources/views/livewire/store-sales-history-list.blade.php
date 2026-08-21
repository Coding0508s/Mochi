<div class="mochi-page space-y-4">
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if($loadError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $loadError }}
        </div>
    @endif

    <div class="mochi-summary-card">
        <h2 class="text-base font-semibold text-mochi-header">Store 전체 판매내역</h2>
        <p class="mt-1 text-xs text-gray-600">그누보드(스토어사이트)의 모든 주문 내역을 시간순으로 조회합니다.</p>
    </div>

    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-end gap-3">
            {{-- 기간 + 조회 (수동 필터 그룹) --}}
            <div class="flex flex-wrap items-end gap-2">
                <div class="flex min-w-[140px] flex-col gap-1">
                    <label for="store-sales-date-start" class="text-xs font-medium text-gray-600">시작일</label>
                    <input id="store-sales-date-start" type="date" wire:model="dateStart" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                </div>
                <div class="flex min-w-[140px] flex-col gap-1">
                    <label for="store-sales-date-end" class="text-xs font-medium text-gray-600">종료일</label>
                    <input id="store-sales-date-end" type="date" wire:model="dateEnd" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                </div>
                <button type="button"
                        wire:click="applyDateFilter"
                        wire:loading.attr="disabled"
                        wire:target="applyDateFilter"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="applyDateFilter">조회</span>
                    <span wire:loading.inline wire:target="applyDateFilter" class="inline-flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        조회 중…
                    </span>
                </button>
            </div>

            {{-- Live 검색 (조회 버튼과 분리) --}}
            <div class="relative min-w-56 flex-1 max-w-md border-l border-gray-200 pl-4 sm:ml-1">
                <label for="store-sales-search" class="mb-1 block text-xs font-medium text-gray-600">검색</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input id="store-sales-search"
                           type="search"
                           wire:model.live.debounce.300ms="search"
                           placeholder="상품명, 기관명, 주문자, 주문번호 검색"
                           class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header"/>
                    <span wire:loading.delay wire:target="search" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2" aria-hidden="true">
                        <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                    </span>
                </div>
            </div>

            {{-- 결과보내기 --}}
            <button
                type="button"
                wire:click="exportToExcel"
                wire:loading.attr="disabled"
                wire:target="exportToExcel"
                @disabled(method_exists($paginatedItems, 'total') && $paginatedItems->total() === 0)
                class="ml-auto inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
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
        </div>
    </div>

    <div class="mochi-table-card overflow-x-auto" aria-live="polite">
        <table class="w-full min-w-[1180px] table-fixed text-sm">
            <colgroup>
                <col class="w-[10.5rem]">
                <col class="w-[9.5rem]">
                <col class="w-[11rem]">
                <col class="w-[8rem]">
                <col>
                <col class="w-[3.5rem]">
                <col class="w-[3.5rem]">
                <col class="w-[5.5rem]">
                <col class="w-[14rem]">
            </colgroup>
            <thead class="mochi-table-head">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold">주문 일시</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">주문번호</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">기관명</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">주문자</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">상품명</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold">수량</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold">상태</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">결제수단</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">전하실 말씀</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($paginatedItems as $item)
                    @php
                        $orderMemo = trim((string) ($item->order_memo ?? ''));
                    @endphp
                    <tr>
                        <td class="overflow-hidden px-3 py-2 whitespace-nowrap text-gray-700">{{ $item->sold_at }}</td>
                        <td class="overflow-hidden px-3 py-2 font-mono text-xs text-gray-700">
                            <span class="block truncate" title="{{ $item->order_ref }}">{{ $item->order_ref }}</span>
                        </td>
                        <td class="overflow-hidden px-3 py-2 break-words text-gray-900">{{ $item->institution_nickname }}</td>
                        <td class="overflow-hidden px-3 py-2 text-gray-900">
                            <span class="block truncate" title="{{ $item->order_customer_name }}">{{ $item->order_customer_name }}</span>
                        </td>
                        <td class="overflow-hidden px-3 py-2 break-words text-gray-900">
                            <span class="text-xs text-gray-500">[{{ $item->product_code }}]</span><br>
                            {{ $item->product_name }}
                        </td>
                        <td class="overflow-hidden px-3 py-2 text-right font-medium whitespace-nowrap text-rose-600">{{ number_format((int) $item->qty) }}</td>
                        <td class="overflow-hidden px-3 py-2 text-center whitespace-nowrap text-gray-700">{{ $item->order_status }}</td>
                        <td class="overflow-hidden px-3 py-2 text-gray-600">
                            <span class="block truncate" title="{{ $item->order_reason }}">{{ $item->order_reason }}</span>
                        </td>
                        <td class="overflow-hidden px-3 py-2 text-gray-600">
                            @if($orderMemo !== '')
                                <span class="line-clamp-2 break-words leading-snug" title="{{ $orderMemo }}">{{ $orderMemo }}</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center text-gray-400">선택한 기간 및 검색어와 일치하는 판매 내역이 없습니다.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-end">
        @if(method_exists($paginatedItems, 'links'))
            {{ $paginatedItems->links() }}
        @endif
    </div>
</div>
