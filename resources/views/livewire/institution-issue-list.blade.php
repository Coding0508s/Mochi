<div class="mochi-page">

    {{-- ───── 플래시 메시지 ───── --}}
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2" data-mochi-flash-dismiss="3000" role="status">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-sm flex items-center gap-2" data-mochi-flash-dismiss="3000" role="status">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    {{-- ───── 상단: 필터 + 버튼 영역 ───── --}}
    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-stretch gap-3 lg:items-center lg:flex-nowrap">

            <select wire:model.live="filterYear"
                    class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header max-lg:flex-1">
                <option value="">전체 년도</option>
                @foreach($years as $year)
                    <option value="{{ $year }}">{{ $year }}년</option>
                @endforeach
            </select>

            <div class="relative min-w-0 flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="기관명, SK코드, 이슈 내용 검색..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"/>
            </div>

            <span class="w-full lg:w-auto shrink-0 whitespace-nowrap text-sm text-gray-500">
                총 <span class="font-semibold text-blue-600">{{ $records->total() }}</span>건
            </span>

            <div class="w-full lg:w-auto lg:ml-auto flex flex-wrap shrink-0 items-center justify-end gap-2 whitespace-nowrap">
                <button type="button"
                        wire:click="$toggle('filterUrgentOnly')"
                        @class([
                            'inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm transition',
                            'border border-red-400 bg-red-50 text-red-800 hover:bg-red-100' => $filterUrgentOnly,
                            'border border-red-300 bg-white text-red-700 hover:bg-red-50' => ! $filterUrgentOnly,
                        ])>
                    긴급 이슈만 보기
                </button>

                <a href="{{ \App\Support\TeamMenuContext::route('supports.create', ['report_mode' => 'issue'], null, 'cs') }}"
                   class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border border-blue-300 bg-white px-3 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4v16m8-8H4"/>
                    </svg>
                    기관 이슈 작성
                </a>
            </div>
        </div>
    </div>

    {{-- ───── 데이터 테이블 ───── --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">SK코드</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">긴급</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">기관명</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">담당 CS</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">발생일</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">시간</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase max-w-64">이슈 내용</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase max-w-64">처리 내역</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase">상태</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                @forelse($records as $index => $record)
                    <tr wire:key="issue-row-{{ $record->ID }}"
                        class="mochi-table-row-hover transition-colors
                               {{ ($record->is_urgent ?? false) ? 'bg-red-50/40' : '' }}
                               {{ $record->isCompleted() ? 'opacity-70' : '' }}">

                        <td class="px-3 py-2.5 text-gray-400 text-xs">
                            {{ $records->firstItem() + $index }}
                        </td>

                        <td class="px-3 py-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                {{ $record->SK_Code ?? '-' }}
                            </span>
                        </td>

                        <td class="px-3 py-2.5">
                            @if($record->is_urgent ?? false)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">
                                    긴급
                                </span>
                            @else
                                <span class="text-xs text-gray-300">-</span>
                            @endif
                        </td>

                        <td class="px-3 py-2.5 font-medium text-gray-900 max-w-40 truncate {{ ($record->is_urgent ?? false) ? 'text-red-700' : '' }}" title="{{ $record->Account_Name }}">
                            {{ $record->Account_Name ?? '-' }}
                        </td>

                        <td class="px-3 py-2.5 text-gray-600 text-xs">
                            {{ $record->TR_Name ?? '-' }}
                        </td>

                        <td class="px-3 py-2.5 text-gray-700">
                            {{ $record->Support_Date?->format('Y-m-d') ?? '-' }}
                        </td>

                        <td class="px-3 py-2.5 text-gray-600 text-xs">
                            {{ $record->Meet_Time ? substr($record->Meet_Time, 0, 5) : '-' }}
                        </td>

                        <td class="px-3 py-2.5 text-gray-600 text-xs max-w-64 truncate" title="{{ $record->Issue }}">
                            {{ $record->Issue ?? '-' }}
                        </td>

                        <td class="px-3 py-2.5 text-gray-600 text-xs max-w-64 truncate" title="{{ $record->TO_Account }}">
                            {{ $record->TO_Account ?? '-' }}
                        </td>

                        <td class="px-3 py-2.5 text-center">
                            @if($record->isCompleted())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">완료</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">진행중</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-3 py-10 text-center text-gray-400 text-sm">
                            등록된 기관 이슈가 없습니다.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $records->links() }}
            </div>
        @endif
    </div>
</div>
