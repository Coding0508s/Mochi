<div class="mochi-page space-y-4">
    @if($loadError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $loadError }}
        </div>
    @endif

    <div class="mochi-summary-card">
        <h2 class="text-base font-semibold text-[#2b78c5]">Store 전체 판매내역</h2>
        <p class="mt-1 text-xs text-gray-600">그누보드(스토어사이트)의 모든 주문 내역을 시간순으로 조회합니다.</p>
    </div>

    <div class="mochi-filter-card flex flex-wrap items-end gap-3">
        <div class="flex min-w-[140px] flex-col gap-1">
            <label class="text-xs font-medium text-gray-600">시작일</label>
            <input type="date" wire:model="dateStart" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="flex min-w-[140px] flex-col gap-1">
            <label class="text-xs font-medium text-gray-600">종료일</label>
            <input type="date" wire:model="dateEnd" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button type="button" wire:click="applyDateFilter" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            조회
        </button>

        <div class="relative ml-auto min-w-64">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="상품명 / 주문자 / 주문번호 검색" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
    </div>

    <div class="mochi-table-card overflow-x-auto">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="mochi-table-head">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold">주문 일시</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">주문번호</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">주문자</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">상품명</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold">수량</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold">상태</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">결제수단</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($paginatedItems as $item)
                    <tr>
                        <td class="px-3 py-2 text-gray-700">{{ $item->sold_at }}</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-700">{{ $item->order_ref }}</td>
                        <td class="px-3 py-2 text-gray-900">{{ $item->order_customer_name }}</td>
                        <td class="px-3 py-2 text-gray-900">
                            <span class="text-xs text-gray-500">[{{ $item->product_code }}]</span><br>
                            {{ $item->product_name }}
                        </td>
                        <td class="px-3 py-2 text-right font-medium text-rose-600">{{ number_format((int) $item->qty) }}</td>
                        <td class="px-3 py-2 text-center text-gray-700">{{ $item->order_status }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $item->order_reason }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400">조회된 판매 내역이 없습니다.</td>
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
