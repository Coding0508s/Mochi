<div class="mochi-page">
    @if($loadError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>{{ $loadError }}</span>
                <button type="button"
                        wire:click="refresh"
                        class="shrink-0 cursor-pointer rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
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
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[10rem] shrink-0">
                <label for="store-inv-category" class="mb-1 block text-xs font-medium text-gray-500">카테고리</label>
                <select id="store-inv-category"
                        wire:model.live="categoryFilter"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all">전체</option>
                    @foreach($this->categoryOptions as $categoryPath)
                        <option value="{{ $categoryPath }}">{{ $categoryPath }}</option>
                    @endforeach
                </select>
            </div>
            <div class="relative min-w-56 flex-1">
                <label for="store-inv-search" class="mb-1 block text-xs font-medium text-gray-500">검색</label>
                <svg class="pointer-events-none absolute bottom-2 left-3 h-4 w-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input id="store-inv-search"
                       type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="품목코드/품목명 검색"
                       class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
            </div>
            @if($search !== '' || $categoryFilter !== 'all')
                <div class="flex shrink-0">
                    <button type="button"
                            wire:click="resetFilters"
                            class="cursor-pointer rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-500 hover:bg-gray-50">
                        초기화
                    </button>
                </div>
            @endif
            <div class="ml-auto flex shrink-0 items-center gap-2">
                @can('manageStoreInventory')
                    <button type="button"
                            wire:click="openSkuModal"
                            class="cursor-pointer rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        품목 추가
                    </button>
                @endcan
                <div class="flex items-center text-sm leading-none text-gray-500">
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
                        <th class="px-3 py-2 text-left text-xs font-semibold">카테고리</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">상품명</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold">이카운트 재고</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold">스토어사이트 재고</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold">알림수량</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($paginatedItems as $index => $item)
                        <tr wire:key="inv-row-{{ $item['product_code'] }}-{{ $index }}">
                            <td class="px-3 py-2 font-mono text-xs text-gray-700">{{ $item['product_code'] }}</td>
                            <td class="max-w-[10rem] truncate px-3 py-2 text-xs text-gray-600" title="{{ $item['category_path'] ?? '' }}">{{ $item['category_path'] ?? '미분류' }}</td>
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
                                                class="cursor-pointer rounded-lg border border-gray-300 px-2 py-1 text-[11px] text-gray-700 hover:bg-gray-50">
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
                            <td colspan="6" class="px-4 py-16 text-center text-gray-400">
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
                            class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50 hover:bg-gray-50">
                        이전
                    </button>
                    <span class="tabular-nums text-gray-700">{{ $this->page }} / {{ $this->lastPage }}</span>
                    <button type="button"
                            wire:click="nextPage"
                            @disabled($this->page >= $this->lastPage)
                            class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50 hover:bg-gray-50">
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
                        <p class="mt-1 text-xs text-gray-500">기준: 이카운트 수불(그누보드 주문 등 자동 반영)</p>
                    </div>
                    <button type="button"
                            wire:click="closeDeductDetail"
                            class="cursor-pointer text-gray-400 transition-colors hover:text-gray-600"
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
                            class="cursor-pointer text-gray-400 transition-colors hover:text-gray-600"
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
            <div class="mochi-modal-shell flex max-h-[min(90vh,calc(100dvh-2rem))] w-full max-w-2xl flex-col overflow-hidden"
                 wire:click.stop>
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">스토어사이트 재고 수정</h3>
                        <p class="mt-1 text-xs text-gray-500">이카운트 창고재고는 기준값으로 유지됩니다.</p>
                    </div>
                    <button type="button"
                            wire:click="closeActualStockModal"
                            class="cursor-pointer text-gray-400 transition-colors hover:text-gray-600"
                            aria-label="모달 닫기">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                @if($saveError)
                    <p class="mx-6 mt-3 shrink-0 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                        {{ $saveError }}
                    </p>
                @endif

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4 text-sm">
                <div class="grid gap-3">
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">상품코드</span>
                        <span class="col-span-2 font-mono text-gray-800">{{ $actualStockModalProductCode !== '' ? $actualStockModalProductCode : '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">상품명</span>
                        <span class="col-span-2 break-words text-gray-800 line-clamp-3" title="{{ $actualStockModalProductName !== '' ? $actualStockModalProductName : '' }}">{{ $actualStockModalProductName !== '' ? $actualStockModalProductName : '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">이카운트 창고재고</span>
                        <span class="col-span-2 text-gray-800">{{ number_format($actualStockModalWarehouseStock) }}</span>
                    </div>

                    <div class="col-span-full rounded-lg border border-amber-200/90 bg-amber-50/70 p-3">
                        <h4 class="text-xs font-semibold text-amber-950">이카운트 최근 차감 (그누보드 주문 등 자동 반영)</h4>
                        <p class="mt-0.5 text-[11px] leading-snug text-amber-900/85">이카운트 창고재고에 반영된 수불 요약입니다. 그누보드 DB가 아닌 이카운트 API 조회 결과입니다.</p>
                        @if($actualStockModalHasEcountDeductSummary)
                            <dl class="mt-2 grid grid-cols-1 gap-1.5 text-xs text-amber-950 sm:grid-cols-2">
                                <div class="flex flex-wrap gap-x-2 border-b border-amber-200/60 pb-1 sm:border-0 sm:pb-0">
                                    <dt class="shrink-0 text-amber-800/90">차감수량</dt>
                                    <dd class="font-medium text-rose-700">
                                        @if(is_numeric($actualStockModalLastDeductQty) && (int) $actualStockModalLastDeductQty > 0)
                                            -{{ number_format((int) $actualStockModalLastDeductQty) }}
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                                <div class="flex flex-wrap gap-x-2 border-b border-amber-200/60 pb-1 sm:border-0 sm:pb-0">
                                    <dt class="shrink-0 text-amber-800/90">발생 일시</dt>
                                    <dd class="font-mono text-gray-900">{{ $actualStockModalLastDeductAtDisplay !== '' && $actualStockModalLastDeductAtDisplay !== '-' ? $actualStockModalLastDeductAtDisplay : '—' }}</dd>
                                </div>
                                <div class="flex flex-wrap gap-x-2 border-b border-amber-200/60 pb-1 sm:border-0 sm:pb-0">
                                    <dt class="shrink-0 text-amber-800/90">차감구분</dt>
                                    <dd class="text-gray-900">{{ $actualStockModalLastDeductType !== '' ? $actualStockModalLastDeductType : '—' }}</dd>
                                </div>
                                <div class="flex flex-wrap gap-x-2 border-b border-amber-200/60 pb-1 sm:border-0 sm:pb-0">
                                    <dt class="shrink-0 text-amber-800/90">전표/참조</dt>
                                    <dd class="break-all text-gray-900">{{ $actualStockModalLastDeductRef !== '' ? $actualStockModalLastDeductRef : '—' }}</dd>
                                </div>
                                <div class="sm:col-span-2 flex flex-col gap-x-2 gap-y-0.5">
                                    <dt class="shrink-0 text-amber-800/90">원인/비고</dt>
                                    <dd class="break-words text-gray-900">{{ $actualStockModalLastDeductReason !== '' ? $actualStockModalLastDeductReason : '—' }}</dd>
                                </div>
                            </dl>
                            <p class="mt-2 text-[11px] text-amber-900/80">아래에서 스토어사이트 재고를 수정하는 것은 별도 동작입니다.</p>
                            <div class="mt-2">
                                <button type="button"
                                        wire:click="switchToEcountDeductDetailFromActualStockModal"
                                        class="cursor-pointer rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-950 hover:bg-amber-100/80">
                                    차감 상세 보기
                                </button>
                                <span class="ml-2 text-[11px] text-amber-900/75">(입력 중이던 내용은 닫힙니다)</span>
                            </div>
                        @else
                            <p class="mt-2 text-xs text-amber-900/80">표시할 최근 차감이 없습니다.</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-3 gap-3 border-b border-gray-100 pb-3">
                        <span class="text-gray-500">현재 스토어사이트 재고</span>
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
                        <label for="actual-stock-new-qty" class="text-gray-500">변경할 스토어사이트 재고</label>
                        <div class="col-span-2">
                            <input id="actual-stock-new-qty"
                                   type="number"
                                   min="0"
                                   wire:model.live.debounce.200ms="actualStockModalNewQty"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-right text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="rounded-lg border border-blue-100 bg-blue-50/80 px-3 py-2 text-sm text-gray-800">
                        @php
                            $trimNew = trim((string) $actualStockModalNewQty);
                            $previewAfter = ($trimNew !== '' && is_numeric($trimNew))
                                ? number_format((int) $trimNew)
                                : '—';
                        @endphp
                        이번 저장: <span class="font-mono font-medium">{{ number_format($actualStockModalCurrentQty) }}</span>
                        → <span class="font-mono font-medium">{{ $previewAfter }}</span>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <label for="actual-stock-memo" class="text-gray-500">
                            변경 사유 <abbr title="필수" class="cursor-help text-red-600 no-underline">*</abbr>
                        </label>
                        <div class="col-span-2">
                            <textarea id="actual-stock-memo"
                                      wire:model.defer="actualStockModalMemo"
                                      rows="3"
                                      maxlength="255"
                                      required
                                      aria-required="true"
                                      placeholder="실사 반영, 오차 수정 등 구체적으로 입력해 주세요."
                                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            <p class="mt-1 text-xs text-gray-500">저장 시 스토어사이트 재고 수정 이력에만 기록됩니다.</p>
                        </div>
                    </div>

                    @if(count($actualStockModalHistoryRows) > 0)
                        <div class="col-span-full rounded-lg border border-gray-200 bg-gray-50/80 p-3">
                            <h4 class="text-xs font-semibold text-gray-700">최근 스토어사이트 재고 수정 이력</h4>
                            <p class="mb-2 mt-0.5 text-[11px] leading-snug text-gray-500">이 화면에서 저장한 스토어사이트 재고만 표시합니다. 이카운트 창고재고 변경은 포함되지 않습니다.</p>
                            <div class="max-h-40 overflow-y-auto rounded border border-gray-200 bg-white">
                                <table class="w-full text-xs">
                                    <thead class="sticky top-0 bg-gray-100 text-left text-gray-600">
                                        <tr>
                                            <th class="px-2 py-1.5 font-medium">일시</th>
                                            <th class="px-2 py-1.5 font-medium">수정자</th>
                                            <th class="px-2 py-1.5 font-medium text-right">변경 전</th>
                                            <th class="px-2 py-1.5 font-medium text-right">변경 후</th>
                                            <th class="px-2 py-1.5 font-medium">사유</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($actualStockModalHistoryRows as $row)
                                            <tr class="text-gray-800">
                                                <td class="whitespace-nowrap px-2 py-1.5 font-mono">{{ $row['changed_at'] }}</td>
                                                <td class="px-2 py-1.5">{{ $row['changed_by_name'] !== '' ? $row['changed_by_name'] : '—' }}</td>
                                                <td class="px-2 py-1.5 text-right font-mono">{{ number_format($row['before_qty']) }}</td>
                                                <td class="px-2 py-1.5 text-right font-mono">{{ number_format($row['after_qty']) }}</td>
                                                <td class="max-w-[10rem] truncate px-2 py-1.5" title="{{ $row['memo'] !== '' ? $row['memo'] : '' }}">{{ $row['memo'] !== '' ? $row['memo'] : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
                </div>

                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <button type="button"
                            wire:click="closeActualStockModal"
                            class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
                        취소
                    </button>
                    <button type="button"
                            wire:click="saveActualStockFromModal"
                            wire:loading.attr="disabled"
                            wire:target="saveActualStockFromModal"
                            class="cursor-pointer rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
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
