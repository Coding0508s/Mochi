<div class="mochi-page">
    @if (session()->has('success'))
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- 상단 요약 --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">Store 재고 품목</h2>
            <span class="text-gray-300">|</span>
            <span class="text-gray-600">이카운트 <span class="font-semibold text-blue-600">PROD_CD</span> 기준 등록</span>
        </div>
    </div>

    {{-- 사용 가이드 --}}
    <div class="mochi-filter-card">
        <h3 class="mb-2 text-sm font-semibold text-gray-800">사용 가이드</h3>
        <ul class="list-disc space-y-1 pl-5 text-xs text-gray-600">
            <li>스토어 재고 화면에는 여기서 <strong class="text-gray-800">활성</strong> 처리된 품목코드만 반영됩니다.</li>
            <li>품목코드는 이카운트의 <strong class="text-gray-800">PROD_CD</strong> 기준으로 입력합니다. (예: <code class="rounded bg-gray-100 px-1">00P228</code>)</li>
            <li>새 품목이 많을 때는 아래 <strong class="text-gray-800">일괄 등록</strong> 영역에 줄바꿈/쉼표로 붙여넣으면 됩니다.</li>
            <li>목록은 <strong class="text-gray-800">품목코드(PROD_CD) 문자 순</strong>으로 정렬됩니다.</li>
        </ul>
    </div>

    {{-- 연동 품목 추가 --}}
    <div class="mochi-filter-card">
        <h3 class="mb-3 text-sm font-semibold text-gray-800">연동 품목 추가</h3>
        <div class="flex flex-wrap items-end gap-3">
            <input type="text"
                   wire:model.defer="newProdCd"
                   placeholder="품목코드 (예: 00P228)"
                   class="min-w-[10rem] max-w-xs flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="text"
                   wire:model.defer="newMemo"
                   placeholder="메모"
                   class="w-36 shrink-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 sm:w-40">
            <button type="button"
                    wire:click="addSku"
                    class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700">
                품목 추가
            </button>
        </div>
        @error('newProdCd')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- 일괄 등록 --}}
    <div class="mochi-filter-card">
        <h3 class="mb-2 text-sm font-semibold text-gray-800">일괄 등록 (CSV/붙여넣기)</h3>
        <p class="mb-3 text-xs text-gray-500">
            품목코드를 쉼표 또는 줄바꿈으로 구분해 입력하세요. 예: <code class="rounded bg-gray-100 px-1">00P228,00P227</code> 또는 줄바꿈 목록
        </p>
        <div class="flex flex-wrap items-end gap-3">
            <textarea wire:model.defer="bulkProdCodes"
                      rows="2"
                      placeholder="00P228,00P227,00P211"
                      class="min-w-[16rem] flex-1 rounded-lg border border-gray-200 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            <button type="button"
                    wire:click="bulkAddSkus"
                    class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700">
                일괄 등록
            </button>
        </div>
        @error('bulkProdCodes')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- 연동 품목 목록 (아코디언) --}}
    <div x-data="{ openSkuList: false }" class="mochi-table-card">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
            <h3 class="text-base font-semibold text-[#2b78c5]">스토어 재고 연동 품목</h3>
            <button type="button"
                    @click="openSkuList = !openSkuList"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                <span x-show="openSkuList">접기</span>
                <span x-show="!openSkuList">펼치기</span>
            </button>
        </div>

        <div x-show="openSkuList" x-cloak>
            <div class="border-b border-gray-100 px-4 py-3">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <div class="relative w-full min-w-56 sm:max-w-xs">
                        <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               placeholder="품목코드 검색"
                               class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="mochi-table-head">
                        <tr class="text-gray-700">
                            <th class="px-3 py-2 text-left text-xs font-semibold">품목코드 <span class="font-normal text-gray-500">(정렬 기준)</span></th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">품목명</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">메모</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold">상태</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold">관리</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($skus as $sku)
                            <tr wire:key="sku-row-{{ $sku->id }}">
                                <td class="px-3 py-2 font-mono text-xs text-gray-800">{{ $sku->prod_cd }}</td>
                                <td class="px-3 py-2 text-sm text-gray-800">{{ $productNamesBySkuId[$sku->id] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <input type="text"
                                           value="{{ $sku->memo ?? '' }}"
                                           wire:change="updateMemo({{ $sku->id }}, $event.target.value)"
                                           class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if($sku->is_active)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">활성</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">비활성</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex flex-col items-center justify-center gap-1.5 sm:flex-row sm:flex-wrap sm:justify-center">
                                        <button type="button"
                                                wire:click="toggleActive({{ $sku->id }})"
                                                class="rounded-lg border border-gray-300 px-2 py-1 text-[11px] text-gray-700 hover:bg-gray-50">
                                            {{ $sku->is_active ? '비활성화' : '활성화' }}
                                        </button>
                                        <button type="button"
                                                wire:click="deleteSku({{ $sku->id }})"
                                                wire:confirm="이 플랫폼 연동만 제거합니다. 이카운트 품목·재고 데이터는 삭제되지 않습니다. 계속할까요?"
                                                class="rounded-lg border border-red-200 bg-white px-2 py-1 text-[11px] text-red-700 hover:bg-red-50">
                                            삭제
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-16 text-center text-gray-400">
                                    <p class="font-medium">등록된 품목이 없습니다.</p>
                                    <p class="mt-1 text-sm">위에서 품목을 추가하거나 일괄 등록해 보세요.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($skus->hasPages())
                <div class="border-t border-gray-200 px-4 py-3">
                    {{ $skus->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
