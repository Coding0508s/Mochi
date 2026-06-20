<div class="mochi-table-card">
    <div class="overflow-x-auto isolate">
        <table class="institution-list-table w-full text-sm whitespace-nowrap">
            <thead class="mochi-table-head">
            <tr class="text-gray-700">
                <th class="institution-sticky-no institution-sticky-no--head px-3 py-2 text-left text-xs font-semibold">No</th>
                <th class="institution-sticky-sk institution-sticky-sk--head px-3 py-2 text-left text-xs font-semibold">
                    <button wire:click="$parent.sort('SKcode')" class="flex items-center gap-1 hover:text-blue-700">
                        SK코드
                        @if($sortField === 'SKcode')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </th>
                <th class="institution-sticky-name institution-sticky-name--head px-3 py-2 text-left text-xs font-semibold">
                    <button wire:click="$parent.sort('AccountName')" class="flex items-center gap-1 hover:text-blue-700">
                        기관명
                        @if($sortField === 'AccountName')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </th>
                <th class="px-3 py-2 text-left text-xs font-semibold">CO</th>
                <th class="px-3 py-2 text-left text-xs font-semibold">TR</th>
                <th class="px-3 py-2 text-left text-xs font-semibold">CS</th>
                <th class="px-3 py-2 text-left text-xs font-semibold">Type</th>
                <th class="px-3 py-2 text-left text-xs font-semibold">구분</th>
                <th class="px-3 py-2 text-left text-xs font-semibold">기관장</th>
                <th class="px-3 py-2 text-left text-xs font-semibold">연락처</th>
                <th class="px-3 py-2 text-left text-xs font-semibold">기관연락처</th>
                <th class="px-3 py-2 text-left text-xs font-semibold">주소</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($institutions as $index => $account)
                @php
                    $master = $account->institution;
                    $customerType = (string) ($account->Customer_Type ?? '');
                    $isTerminated = str_contains($customerType, '해지');
                    $customerTypeWithoutTerminateBadge = $customerType;
                    if ($isTerminated) {
                        $customerTypeWithoutTerminateBadge = trim((string) preg_replace('/^해지$/u', '', $customerTypeWithoutTerminateBadge));
                        $customerTypeWithoutTerminateBadge = trim((string) preg_replace('/^해지\s+/u', '', $customerTypeWithoutTerminateBadge));
                        $customerTypeWithoutTerminateBadge = trim((string) preg_replace('/\s+해지$/u', '', $customerTypeWithoutTerminateBadge));
                        $customerTypeWithoutTerminateBadge = trim((string) preg_replace('/\s+/u', ' ', $customerTypeWithoutTerminateBadge));
                    }
                @endphp
                <tr wire:key="institution-row-{{ $account->ID }}"
                    wire:click="selectRow({{ $account->ID }})"
                    class="mochi-table-row-hover transition-colors cursor-pointer">
                    <td class="institution-sticky-no px-3 py-2 text-gray-500 text-xs">{{ $institutions->firstItem() + $index }}</td>
                    <td class="institution-sticky-sk px-3 py-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                            {{ $account->SK_Code ?? '-' }}
                        </span>
                    </td>
                    <td class="institution-sticky-name px-3 py-2 font-medium">
                        <span class="text-blue-700 hover:underline">
                            {{ $account->Account_Name ?: ($master?->AccountName ?? '-') }}
                        </span>
                        @if($master?->EnglishName)
                            <span class="block text-xs text-gray-400">{{ $master->EnglishName }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-gray-600">{{ $account->CO ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-600">{{ $account->TR ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-600">{{ $account->CS ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-600 max-w-[14rem] min-w-0">
                        @if($customerType === '')
                            <span class="text-gray-400">-</span>
                        @else
                            <div class="flex min-w-0 items-center gap-1">
                                @if($isTerminated)
                                    <span class="inline-flex shrink-0 items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                        해지
                                    </span>
                                @endif
                                @if($customerTypeWithoutTerminateBadge !== '')
                                    <span class="min-w-0 truncate text-xs" title="{{ $customerType }}">{{ $customerTypeWithoutTerminateBadge }}</span>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td class="px-3 py-2">
                        @if($master?->Gubun)
                            <span class="text-xs text-gray-600">{{ $master->Gubun }}</span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-gray-600">{{ $master?->Director ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-600">{{ $master?->Phone ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-600">{{ $master?->AccountTel ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-500 max-w-56 truncate" title="{{ $account->Address ?: $master?->Address }}">
                        {{ $account->Address ?: ($master?->Address ?? '-') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="px-4 py-16 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="font-medium">검색 결과가 없습니다</p>
                        <p class="text-sm mt-1">다른 조건으로 다시 검색해 보세요.</p>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($institutions->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $institutions->links() }}
        </div>
    @endif
</div>
