<div class="mochi-page">
    {{-- 상단 안내 --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-green-700">잠재기관 보기</h2>
            <span class="text-gray-300">|</span>
            <span class="text-gray-600">
                조회 기간: <span class="font-semibold text-gray-800">{{ $periodLabel }}</span>
                @if($periodGranularity === 'month')
                    <span class="text-gray-400 text-xs ml-1">(월별)</span>
                @else
                    <span class="text-gray-400 text-xs ml-1">(연도별)</span>
                @endif
            </span>
            <div class="ml-auto text-gray-500">
                현재 조건 결과: <span class="font-semibold text-gray-700">{{ $totalCount }}</span>건
            </div>
        </div>
    </div>

    {{-- 필터 --}}
    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-4">
            <fieldset class="flex flex-wrap items-center gap-3 border-0 p-0 m-0">
                <span class="text-sm text-gray-600">구간</span>
                <label class="inline-flex items-center gap-1.5 text-sm cursor-pointer">
                    <input type="radio" wire:model.live="periodGranularity" value="month" class="rounded-full border-gray-300 text-blue-600 focus:ring-blue-500" />
                    월별
                </label>
                <label class="inline-flex items-center gap-1.5 text-sm cursor-pointer">
                    <input type="radio" wire:model.live="periodGranularity" value="year" class="rounded-full border-gray-300 text-blue-600 focus:ring-blue-500" />
                    연도별
                </label>
            </fieldset>

            @if($periodGranularity === 'month')
                <div class="flex items-center gap-2">
                    <label for="piv-year-month" class="text-sm text-gray-600 whitespace-nowrap">연·월</label>
                    <input id="piv-year-month"
                           type="month"
                           wire:model.live="yearMonth"
                           class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
            @else
                <div class="flex items-center gap-2">
                    <label for="piv-filter-year" class="text-sm text-gray-600 whitespace-nowrap">연도</label>
                    <select id="piv-filter-year"
                            wire:model.live="filterYear"
                            class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-[7rem]">
                        @foreach($yearOptions as $y)
                            <option value="{{ $y }}">{{ $y }}년</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <fieldset class="flex flex-wrap items-center gap-3 border-0 p-0 m-0">
                <span class="text-sm text-gray-600">기준</span>
                <label class="inline-flex items-center gap-1.5 text-sm cursor-pointer">
                    <input type="radio" wire:model.live="dateBasis" value="created" class="rounded-full border-gray-300 text-blue-600 focus:ring-blue-500" />
                    등록일
                </label>
                <label class="inline-flex items-center gap-1.5 text-sm cursor-pointer">
                    <input type="radio" wire:model.live="dateBasis" value="meeting" class="rounded-full border-gray-300 text-blue-600 focus:ring-blue-500" />
                    미팅일
                </label>
            </fieldset>

            <div class="relative flex-1 min-w-56">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="search"
                       wire:model.live.debounce.300ms="search"
                       placeholder="검색 (기관명·코드·담당 등)"
                       autocomplete="off"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">
            월별: 선택한 달 안의 등록일·미팅일만 조회합니다. 연도별: 해당 연도 1월 1일~12월 31일까지입니다.
            등록일은 잠재기관 최초 등록일, 미팅일은 상담·미팅 일정 기준입니다.
        </p>
    </div>

    {{-- 테이블: 등록일 --}}
    @if($basisCreated)
        <div class="mochi-table-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="mochi-table-head">
                        <tr class="text-gray-700">
                            <th class="px-3 py-2 text-left text-xs font-semibold">No</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">ID</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">담당자</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">등록일</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">신규구분</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">컨설팅타입</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">기관명</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold">LS</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold">GS(유)</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold">GS(초)</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold">합계</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($targets as $index => $target)
                            <tr wire:key="piv-created-{{ $target->ID }}" class="mochi-table-row-hover transition-colors">
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ $targets->firstItem() + $index }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $target->ID }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $target->AccountManager ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $target->CreatedDate?->format('Y-m-d') ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $target->Type ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $target->Gubun ?? '-' }}</td>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ $target->AccountName ?? '-' }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $target->LS ?? 0 }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $target->GS_K ?? 0 }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $target->GS_E ?? 0 }}</td>
                                <td class="px-3 py-2 text-center font-semibold text-gray-800">{{ $target->Total ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-16 text-center text-gray-400">
                                    <p class="font-medium">해당 월에 등록된 잠재기관이 없습니다</p>
                                    <p class="text-sm mt-1">연·월 또는 검색어를 바꿔 보세요.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($targets->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $targets->links() }}
                </div>
            @endif
        </div>
    @else
        {{-- 테이블: 미팅일 --}}
        <div class="mochi-table-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="mochi-table-head">
                        <tr class="text-gray-700">
                            <th class="px-3 py-2 text-left text-xs font-semibold">No</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">ID</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">미팅일</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">시간</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">기관명</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">담당자</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">상담유형</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">가능성</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold min-w-[12rem]">내용</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($meetings as $index => $row)
                            <tr wire:key="piv-meeting-{{ $row->ID }}" class="mochi-table-row-hover transition-colors">
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ $meetings->firstItem() + $index }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row->ID }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row->MeetingDate?->format('Y-m-d') ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-700">
                                    @if(filled($row->MeetingTime))
                                        {{ $row->MeetingTime }}
                                        @if(filled($row->MeetingTime_End))
                                            ~ {{ $row->MeetingTime_End }}
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ $row->AccountName ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row->AccountManager ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row->ConsultingType ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row->Possibility ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-600 max-w-md truncate" title="{{ $row->Description ?? '' }}">
                                    {{ $row->Description ? \Illuminate\Support\Str::limit($row->Description, 80) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-16 text-center text-gray-400">
                                    <p class="font-medium">해당 월에 예정된 미팅·상담이 없습니다</p>
                                    <p class="text-sm mt-1">연·월 또는 검색어를 바꿔 보세요.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($meetings->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $meetings->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
