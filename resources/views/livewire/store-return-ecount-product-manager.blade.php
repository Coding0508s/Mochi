<div class="mochi-page">
    @if (session()->has('success'))
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700" data-mochi-flash-dismiss="3000" role="status">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-mochi-header">반품 등록 품목</h2>
            <span class="text-gray-300">|</span>
            <span class="text-gray-600">이카운트 <span class="font-semibold text-blue-600">PROD_CD</span> 기준 · Store 재고와 별도 관리</span>
        </div>
    </div>

    <div class="mochi-filter-card">
        <h3 class="mb-2 text-sm font-semibold text-gray-800">사용 가이드</h3>
        <ul class="list-disc space-y-1 pl-5 text-xs text-gray-600">
            <li>여기서 <strong class="text-gray-800">활성</strong> 처리된 품목만 반품 등록 화면 품목 드롭다운에 표시됩니다.</li>
            <li>품목코드는 이카운트 <strong class="text-gray-800">PROD_CD</strong> 기준입니다. (예: <code class="rounded bg-gray-100 px-1">00P228</code>)</li>
            <li>Store 재고 연동 품목과는 <strong class="text-gray-800">독립</strong>으로 관리됩니다.</li>
            <li>품목이 많을 때는 <strong class="text-gray-800">일괄 등록</strong>에 쉼표 또는 줄바꿈으로 붙여넣으세요.</li>
        </ul>
    </div>

    <div class="mochi-filter-card">
        <h3 class="mb-3 text-sm font-semibold text-gray-800">품목 추가</h3>
        <div class="flex flex-wrap items-end gap-3">
            <input type="text"
                   wire:model.defer="newProdCd"
                   placeholder="품목코드 (예: 00P228)"
                   class="min-w-[10rem] max-w-xs flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
            <input type="text"
                   wire:model.defer="newMemo"
                   placeholder="메모"
                   class="w-36 shrink-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header sm:w-40">
            <button type="button"
                    wire:click="addProduct"
                    class="shrink-0 rounded-lg bg-mochi-header px-4 py-2 text-xs font-medium text-white hover:opacity-90">
                품목 추가
            </button>
        </div>
        @error('newProdCd')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="mochi-filter-card">
        <h3 class="mb-2 text-sm font-semibold text-gray-800">일괄 등록</h3>
        <p class="mb-3 text-xs text-gray-500">
            품목코드를 쉼표 또는 줄바꿈으로 구분해 입력하세요.
        </p>
        <div class="flex flex-wrap items-end gap-3">
            <textarea wire:model.defer="bulkProdCodes"
                      rows="2"
                      placeholder="00P228,00P227,00P211"
                      class="min-w-[16rem] flex-1 rounded-lg border border-gray-200 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-mochi-header"></textarea>
            <button type="button"
                    wire:click="bulkAddProducts"
                    class="shrink-0 rounded-lg bg-mochi-header px-4 py-2 text-xs font-medium text-white hover:opacity-90">
                일괄 등록
            </button>
        </div>
        @error('bulkProdCodes')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="mochi-table-card">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-gray-200 px-4 py-3">
            <h3 class="min-w-0 flex-1 text-base font-semibold text-mochi-header">등록 품목 목록</h3>
            <div class="relative w-full min-w-[12rem] max-w-xs sm:w-56">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="품목코드 검색"
                       class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="px-3 py-2 text-left text-xs font-semibold whitespace-nowrap">품목코드</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold whitespace-nowrap">이카운트 품목명</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold whitespace-nowrap">메모</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold whitespace-nowrap">상태</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold whitespace-nowrap">관리</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($products as $product)
                        <tr wire:key="return-product-row-{{ $product->id }}">
                            <td class="px-3 py-2 align-middle font-mono text-xs text-gray-800">{{ $product->prod_cd }}</td>
                            <td class="px-3 py-2 align-middle text-sm text-gray-800">{{ $productNamesById[$product->id] ?? '-' }}</td>
                            <td class="px-3 py-2 align-middle">
                                <input type="text"
                                       value="{{ $product->memo }}"
                                       wire:change="updateMemo({{ $product->id }}, $event.target.value)"
                                       placeholder="메모"
                                       class="w-full min-w-[8rem] rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            </td>
                            <td class="px-3 py-2 text-center align-middle">
                                <button type="button"
                                        wire:click="toggleActive({{ $product->id }})"
                                        class="rounded-full px-2.5 py-1 text-xs font-medium {{ $product->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $product->is_active ? '활성' : '비활성' }}
                                </button>
                            </td>
                            <td class="px-3 py-2 text-center align-middle">
                                <button type="button"
                                        wire:click="deleteProduct({{ $product->id }})"
                                        wire:confirm="이 목록에서만 제거합니다. 이카운트 품목은 삭제되지 않습니다. 계속할까요?"
                                        class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-50 hover:text-red-600">
                                    제거
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">
                                등록된 반품 품목이 없습니다. 품목코드를 추가해 주세요.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
