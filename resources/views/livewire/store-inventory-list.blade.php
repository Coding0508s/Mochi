<div class="mochi-page">
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

    @if($saveError)
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $saveError }}
        </div>
    @endif

    @if($saveSuccess)
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ $saveSuccess }}
        </div>
    @endif

    {{-- 상단 요약 --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">상품재고관리</h2>
            <span class="text-gray-300">|</span>
            <span class="text-gray-600">이카운트 <span class="font-semibold text-blue-600">창고재고</span> 기준</span>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-800">조회 전용</span>
        </div>
    </div>

    {{-- 검색 --}}
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
            <div class="ml-auto flex items-center gap-2">
                @can('manageStoreInventory')
                    <button type="button"
                            wire:click="openSkuModal"
                            class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        품목 추가
                    </button>
                @endcan
                <div class="text-gray-500">
                    전체 <span class="font-semibold text-gray-700">{{ number_format($this->totalItems) }}</span>건
                </div>
            </div>
        </div>
    </div>

    {{-- 목록 --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="px-3 py-2 text-left text-xs font-semibold">상품코드</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">상품명</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold">이카운트 재고</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold">스토어 사이트 재고</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold">알림수량</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($paginatedItems as $index => $item)
                        <tr wire:key="inv-row-{{ $item['product_code'] }}-{{ $index }}">
                            <td class="px-3 py-2 font-mono text-xs text-gray-700">{{ $item['product_code'] }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded border border-gray-100 bg-gray-50">
                                        @if($item['image_url'] !== '')
                                            <img src="{{ $item['image_url'] }}" alt="" class="h-full w-full object-cover">
                                        @endif
                                    </div>
                                    <span class="font-medium text-gray-900">{{ $item['product_name'] }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ number_format((int) $item['warehouse_stock']) }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">
                                @can('manageStoreInventory')
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="font-medium text-gray-800">{{ number_format((int) ($item['actual_stock_quantity'] ?? 0)) }}</span>
                                        <button type="button"
                                                wire:click="openActualStockModal('{{ $item['product_code'] }}')"
                                                class="rounded-lg border border-gray-300 px-2 py-1 text-[11px] text-gray-700 hover:bg-gray-50">
                                            수정
                                        </button>
                                    </div>
                                @else
                                    {{ number_format((int) ($item['actual_stock_quantity'] ?? 0)) }}
                                @endcan
                            </td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ number_format((int) ($item['notify_quantity'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center text-gray-400">
                                <p class="font-medium">재고 데이터가 없습니다.</p>
                                @if($loadError === null)
                                    <p class="mt-1 text-sm">검색어를 바꾸거나 잠시 후 다시 불러와 보세요.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->totalItems > 0)
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

    @if($showDeductDetailModal)
        @php
            $deductType = trim((string) ($selectedDeductItem['last_deduct_type'] ?? ''));
            $deductReason = trim((string) ($selectedDeductItem['last_deduct_reason'] ?? ''));
            $deductRef = trim((string) ($selectedDeductItem['last_deduct_ref'] ?? ''));
            $deductAt = trim((string) ($selectedDeductItem['last_deduct_at_display'] ?? '-'));
            $deductQty = $selectedDeductItem['last_deduct_qty'] ?? null;
        @endphp
        <div class="mochi-modal-overlay" wire:key="store-inventory-deduct-detail-modal" wire:click.self="closeDeductDetail">
            <div class="mochi-modal-shell max-w-3xl">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">최근 차감 상세</h3>
                        <p class="mt-1 text-xs text-gray-500">기준: 이카운트 수불 이력</p>
                    </div>
                    <button type="button"
                            wire:click="closeDeductDetail"
                            class="text-gray-400 transition-colors hover:text-gray-600"
                            aria-label="모달 닫기">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="grid gap-3 p-6 text-sm">
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">상품코드</span>
                        <span class="col-span-2 font-mono text-gray-800">{{ $selectedDeductItem['product_code'] ?? '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">차감수량</span>
                        <span class="col-span-2 font-medium text-rose-600">
                            @if(is_numeric($deductQty) && (int) $deductQty > 0)
                                -{{ number_format((int) $deductQty) }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">발생 일시</span>
                        <span class="col-span-2 text-gray-800">{{ $deductAt !== '' ? $deductAt : '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">차감구분</span>
                        <span class="col-span-2 text-gray-800">{{ $deductType !== '' ? $deductType : '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">전표/참조번호</span>
                        <span class="col-span-2 text-gray-800">{{ $deductRef !== '' ? $deductRef : '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <span class="text-gray-500">원인/비고</span>
                        <span class="col-span-2 text-gray-800">{{ $deductReason !== '' ? $deductReason : '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showSkuModal)
        <div class="mochi-modal-overlay" wire:key="store-inventory-sku-modal" wire:click.self="closeSkuModal">
            <div class="mochi-modal-shell max-w-5xl">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Store 재고 품목 관리</h3>
                        <p class="mt-1 text-xs text-gray-500">등록/비활성/정렬을 변경하면 재고 목록에 즉시 반영됩니다.</p>
                    </div>
                    <button type="button"
                            wire:click="closeSkuModal"
                            class="text-gray-400 transition-colors hover:text-gray-600"
                            aria-label="모달 닫기">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="max-h-[85vh] overflow-y-auto p-6">
                    <livewire:store-inventory-sku-manager :key="'store-inventory-sku-manager-'.$skuModalInstance" />
                </div>
            </div>
        </div>
    @endif

    @if($showActualStockModal)
        <div class="mochi-modal-overlay" wire:key="store-inventory-actual-stock-modal" wire:click.self="closeActualStockModal">
            <div class="mochi-modal-shell max-w-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">실제수량 수정 (스토어 사이트 재고)</h3>
                        <p class="mt-1 text-xs text-gray-500">이카운트 창고재고는 기준값으로 유지됩니다.</p>
                    </div>
                    <button type="button"
                            wire:click="closeActualStockModal"
                            class="text-gray-400 transition-colors hover:text-gray-600"
                            aria-label="모달 닫기">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="grid gap-3 p-6 text-sm">
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">상품코드</span>
                        <span class="col-span-2 font-mono text-gray-800">{{ $actualStockModalProductCode !== '' ? $actualStockModalProductCode : '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">상품명</span>
                        <span class="col-span-2 text-gray-800">{{ $actualStockModalProductName !== '' ? $actualStockModalProductName : '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">이카운트 창고재고</span>
                        <span class="col-span-2 text-gray-800">{{ number_format($actualStockModalWarehouseStock) }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">현재 실제수량</span>
                        <span class="col-span-2 text-gray-800">{{ number_format($actualStockModalCurrentQty) }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">마지막 수정자</span>
                        <span class="col-span-2 text-gray-800">{{ $actualStockModalLastChangedBy !== '' ? $actualStockModalLastChangedBy : '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">마지막 수정시각</span>
                        <span class="col-span-2 text-gray-800">{{ $actualStockModalLastChangedAt !== '' ? $actualStockModalLastChangedAt : '-' }}</span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <label for="actual-stock-new-qty" class="text-gray-500">새 실제수량</label>
                        <div class="col-span-2">
                            <input id="actual-stock-new-qty"
                                   type="number"
                                   min="0"
                                   wire:model.defer="actualStockModalNewQty"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-right text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <label for="actual-stock-memo" class="text-gray-500">변경 사유(선택)</label>
                        <div class="col-span-2">
                            <textarea id="actual-stock-memo"
                                      wire:model.defer="actualStockModalMemo"
                                      rows="3"
                                      maxlength="255"
                                      placeholder="변경 사유를 입력하면 이력에 함께 저장됩니다."
                                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <button type="button"
                            wire:click="closeActualStockModal"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
                        취소
                    </button>
                    <button type="button"
                            wire:click="saveActualStockFromModal"
                            wire:loading.attr="disabled"
                            wire:target="saveActualStockFromModal"
                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        저장
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
            재고 데이터를 불러오는 중...
        </div>
    </div>
</div>
