<div class="mochi-page">
    @php($salesSource = strtolower((string) config('store.sales_history_source', config('store.data_source', 'ecount'))))
    @if($loadError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>{{ $loadError }}</span>
                <button type="button"
                        wire:click="refresh"
                        class="shrink-0 rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
                    다시 시도
                </button>
            </div>
        </div>
    @endif

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">Store 판매내역</h2>
            <span class="text-gray-300">|</span>
            @if($salesSource === 'gnuboard')
                <span class="text-gray-600">스토어 사이트 주문 기준 최근 판매 내역 <span class="font-semibold text-blue-600">최대 5건</span></span>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-800">스토어 사이트 DB 조회</span>
            @else
                <span class="text-gray-600">등록 상품별 최근 출고 내역 <span class="font-semibold text-blue-600">최대 5건</span></span>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-800">이카운트 API 조회 전용</span>
            @endif
            <span class="text-gray-500">조회 기준 <span class="font-semibold text-gray-700">{{ $salesListDateRangeLabel }}</span></span>
            <span class="text-gray-500">(최근 <span class="font-semibold text-gray-700">90일 이내</span> 판매·출고 품목만 노출)</span>
        </div>
    </div>

    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-56 flex-1">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="품목코드/품목명 검색"
                       class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
            </div>
            @if($search)
                <button type="button"
                        wire:click="$set('search', '')"
                        class="cursor-pointer rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-500 hover:bg-gray-50">
                    초기화
                </button>
            @endif
            <div class="ml-auto text-gray-500">
                전체 <span class="font-semibold text-gray-700">{{ number_format($this->totalItems) }}</span>건
            </div>
        </div>
    </div>

    {{-- 목록 (store-inventory-list 등과 동일 테이블 토큰) --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="w-12 px-3 py-2 text-center text-xs font-semibold">No</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">상품코드</th>
                        <th class="min-w-[140px] px-3 py-2 text-left text-xs font-semibold">상품명</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold">내역 건수</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold">총 출고수량</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">최근 일시</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($paginatedItems as $index => $item)
                        <tr wire:key="sales-row-{{ $item['row_key'] }}"
                            wire:click="selectProductRow('{{ $item['row_key'] }}')"
                            @class([
                                'mochi-table-row-hover cursor-pointer transition-colors',
                                'bg-blue-50' => $showSalesDetailModal && ($selectedRowKey ?? null) === ($item['row_key'] ?? ''),
                            ])>
                            <td class="px-3 py-2 text-center text-xs text-gray-500">{{ ($this->page - 1) * $this->perPage + $index + 1 }}</td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-700">{{ $item['product_code'] }}</td>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ $item['product_name'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ number_format((int) ($item['history_count'] ?? 0)) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-700">
                                @if((int) ($item['history_total_qty'] ?? 0) > 0)
                                    <span class="font-medium text-rose-600">-{{ number_format((int) ($item['history_total_qty'] ?? 0)) }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 tabular-nums text-gray-700">{{ $item['history_latest_at'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center text-gray-400">
                                <p class="font-medium">판매내역 데이터가 없습니다.</p>
                                @if($loadError === null)
                                    <p class="mt-1 text-sm">검색어를 바꾸거나 잠시 후 다시 불러와 보세요.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($this->totalItems > 0)
                    <tfoot>
                        <tr class="border-t border-gray-200 bg-gray-50 text-gray-800">
                            <td class="px-3 py-2 text-sm font-semibold" colspan="3">현재 페이지 합계</td>
                            <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums">{{ number_format($masterSummary['history_rows']) }}</td>
                            <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums">
                                @if($masterSummary['total_qty'] > 0)
                                    <span class="text-rose-600">-{{ number_format($masterSummary['total_qty']) }}</span>
                                @else
                                    0
                                @endif
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-500">—</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if($this->totalItems > 0)
            <div class="border-t border-gray-200 px-4 py-2 text-center text-xs text-gray-500">
                품목 행을 클릭하면 <span class="font-medium text-gray-700">판매 내역 상세</span>가 모달로 열립니다. (같은 행을 다시 클릭하면 닫힙니다.)
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-4 py-3 text-xs text-gray-600">
                <span>
                    {{ number_format($this->totalItems) }}건 중
                    {{ number_format($this->totalItems > 0 ? (($this->page - 1) * $this->perPage + 1) : 0) }}–
                    {{ number_format(min($this->page * $this->perPage, $this->totalItems)) }}건 표시
                </span>
                <div class="flex items-center gap-2">
                    <button type="button"
                            wire:click="previousPage"
                            @disabled($this->page <= 1)
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50 hover:bg-gray-50">
                        이전
                    </button>
                    <span class="tabular-nums text-gray-700">{{ $this->page }} / {{ $this->lastPage }}</span>
                    <button type="button"
                            wire:click="nextPage"
                            @disabled($this->page >= $this->lastPage)
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50 hover:bg-gray-50">
                        다음
                    </button>
                </div>
            </div>
        @endif
    </div>

    @if($showSalesDetailModal && $selectedSalesItem)
        <div class="mochi-modal-overlay z-[60]" wire:key="store-sales-detail-modal-{{ $selectedSalesItem['row_key'] ?? 'x' }}" wire:click.self="closeSalesDetailModal">
            <div class="mochi-modal-shell max-w-5xl">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">판매 내역 상세</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            <span class="font-mono font-medium text-gray-800">{{ $selectedSalesItem['product_code'] ?? '' }}</span>
                            @if(($selectedSalesItem['product_name'] ?? '') !== '')
                                <span class="text-gray-400">·</span> {{ $selectedSalesItem['product_name'] }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-gray-500">조회 기간은 <span class="font-medium text-gray-700">최대 90일</span>까지 설정할 수 있습니다.</p>
                    </div>
                    <button type="button"
                            wire:click="closeSalesDetailModal"
                            class="shrink-0 text-gray-400 transition-colors hover:text-gray-600"
                            aria-label="모달 닫기">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 px-6 py-4">
                    <div class="flex min-w-[140px] flex-col gap-1">
                        <label for="sales-modal-date-start" class="text-xs font-medium text-gray-600">시작일</label>
                        <input id="sales-modal-date-start"
                               type="date"
                               wire:model="modalDateStart"
                               class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex min-w-[140px] flex-col gap-1">
                        <label for="sales-modal-date-end" class="text-xs font-medium text-gray-600">종료일</label>
                        <input id="sales-modal-date-end"
                               type="date"
                               wire:model="modalDateEnd"
                               class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button"
                                wire:click="applyModalDateFilter"
                                wire:loading.attr="disabled"
                                wire:target="applyModalDateFilter"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                            조회
                        </button>
                        <span wire:loading wire:target="applyModalDateFilter" class="text-xs text-gray-500">불러오는 중…</span>
                    </div>
                    <p class="w-full text-xs leading-snug text-gray-500 sm:w-auto sm:flex-1">
                        처음에는 목록과 동일한 최근 내역이 표시됩니다. 기간을 바꾼 뒤 <span class="font-medium text-gray-700">조회</span>를 누르면 해당 기간으로 다시 불러옵니다.
                    </p>
                </div>
                @if($modalHistoryError)
                    <div class="border-b border-red-200 bg-red-50 px-6 py-2 text-sm text-red-700">
                        {{ $modalHistoryError }}
                    </div>
                @endif
                <div class="max-h-[min(75vh,560px)] overflow-x-auto overflow-y-auto px-6 py-4">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="mochi-table-head">
                            <tr class="text-gray-700">
                                <th class="w-10 px-3 py-2 text-center text-xs font-semibold">No</th>
                                <th class="w-36 px-3 py-2 text-left text-xs font-semibold">일시</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold">수량</th>
                                <th class="min-w-[72px] px-3 py-2 text-left text-xs font-semibold">주문자</th>
                                <th class="min-w-[72px] px-3 py-2 text-left text-xs font-semibold">구분</th>
                                <th class="min-w-[100px] px-3 py-2 text-left text-xs font-semibold">주문·전표번호</th>
                                <th class="min-w-[120px] px-3 py-2 text-left text-xs font-semibold">비고</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($modalDetailHistories as $hIndex => $history)
                                <tr wire:key="sales-detail-modal-{{ $selectedSalesItem['row_key'] }}-{{ $hIndex }}">
                                    <td class="px-3 py-2 text-center text-xs text-gray-500">{{ $hIndex + 1 }}</td>
                                    <td class="px-3 py-2 tabular-nums text-gray-700">{{ ($history['at'] ?? '') !== '' ? $history['at'] : '-' }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-rose-600">{{ $history['qty_display'] ?? '-' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ ($history['order_customer_name'] ?? '') !== '' ? $history['order_customer_name'] : '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ ($history['type'] ?? '') !== '' ? $history['type'] : '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-700">{{ ($history['ref'] ?? '') !== '' ? $history['ref'] : '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ ($history['reason'] ?? '') !== '' ? $history['reason'] : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-16 text-center text-gray-400">
                                        <p class="font-medium">표시할 내역이 없습니다.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <span class="text-xs text-gray-600">
                        조회 결과 <span class="font-semibold text-gray-800">{{ number_format(count($modalDetailHistories)) }}</span>건
                    </span>
                    <button type="button"
                            wire:click="closeSalesDetailModal"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
                        닫기
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div wire:loading.delay wire:target="refresh" class="fixed bottom-6 right-6 z-50">
        <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-lg">
            <svg class="h-4 w-4 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            판매내역 데이터를 불러오는 중...
        </div>
    </div>
</div>
