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
                            @can('managePotentialInstitutions')
                                <th class="px-3 py-2 text-center text-xs font-semibold">지원보고서</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($targets as $index => $target)
                            <tr wire:key="piv-created-{{ $target->ID }}"
                                wire:click="openTargetDetail({{ $target->ID }})"
                                class="mochi-table-row-hover transition-colors cursor-pointer">
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
                                @can('managePotentialInstitutions')
                                    <td class="px-3 py-2 text-center" onclick="event.stopPropagation()">
                                        @if($target->IsContract ?? false)
                                            <a href="{{ route('supports.create', ['potential_target_id' => $target->ID]) }}"
                                               class="text-xs font-medium text-blue-600 hover:text-blue-800 underline">작성</a>
                                        @else
                                            <span class="text-xs text-gray-400" title="계약·SK 발급 후 작성 가능">-</span>
                                        @endif
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ \Illuminate\Support\Facades\Gate::allows('managePotentialInstitutions') ? 12 : 11 }}" class="px-4 py-16 text-center text-gray-400">
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
                            <tr wire:key="piv-meeting-{{ $row->ID }}"
                                wire:click="openTargetDetailFromMeeting({{ $row->ID }})"
                                class="mochi-table-row-hover transition-colors cursor-pointer">
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

    @if($showDetailModal && $selectedTarget)
        <div class="mochi-modal-overlay" wire:click.self="closeDetailModal">
            <div class="mochi-modal-shell max-w-4xl h-[80vh] max-h-[80vh] flex flex-col" wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50/80 to-white">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">잠재기관 상세 정보</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $selectedTarget['account_name'] }} (ID: {{ $selectedTarget['id'] }})
                        </p>
                    </div>
                    <button wire:click="closeDetailModal" class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 text-sm flex-1 overflow-y-auto space-y-4">
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">코드</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['account_code'] }}</td>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당자</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['account_manager'] }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">등록일</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['created_date'] }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">계약여부</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['is_contract'] ? '계약' : '미계약' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">신규구분</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['type'] }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">컨설팅타입</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['gubun'] }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">원장</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['director'] }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">연락처</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['phone'] }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">LS / GS(유) / GS(초)</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['ls'] }} / {{ $selectedTarget['gs_k'] }} / {{ $selectedTarget['gs_e'] }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">합계</th>
                                    <td class="px-3 py-2 font-semibold text-gray-900">{{ $selectedTarget['total'] }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">주소</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['address'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if(!($selectedTarget['is_contract'] ?? false))
                        @can('managePotentialInstitutions')
                            <livewire:potential-institution-meeting-form
                                :co-new-target-id="$selectedTarget['id']"
                                :wire:key="'pim-form-'.$selectedTarget['id']"
                            />
                        @endcan
                    @endif

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-bold text-[#1f4f8f]">미팅/컨설팅 이력</h4>
                            <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                                총 {{ count($detailMeetings) }}건
                            </span>
                        </div>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="max-h-44 overflow-y-auto overflow-x-auto">
                                <table class="w-full text-xs whitespace-nowrap">
                                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                                        <tr class="text-gray-600">
                                            <th class="px-3 py-2 text-left">일자</th>
                                            <th class="px-3 py-2 text-left">시간</th>
                                            <th class="px-3 py-2 text-left">담당자</th>
                                            <th class="px-3 py-2 text-left">유형</th>
                                            <th class="px-3 py-2 text-left">내용</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($detailMeetings as $meeting)
                                            <tr>
                                                <td class="px-3 py-2">{{ $meeting['meeting_date'] }}</td>
                                                <td class="px-3 py-2">{{ $meeting['meeting_time'] }} ~ {{ $meeting['meeting_time_end'] }}</td>
                                                <td class="px-3 py-2">{{ $meeting['account_manager'] }}</td>
                                                <td class="px-3 py-2">{{ $meeting['consulting_type'] }}</td>
                                                <td class="px-3 py-2 max-w-72 whitespace-normal break-words">{{ \Illuminate\Support\Str::limit($meeting['description'], 100) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-3 py-8 text-center text-gray-400">미팅/컨설팅 이력이 없습니다.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <h4 class="text-sm font-bold text-[#1f4f8f]">기관지원보고서 이력</h4>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                                    총 {{ count($detailSupportRecords) }}건
                                </span>
                                @can('managePotentialInstitutions')
                                    @if($selectedTarget['is_contract'] ?? false)
                                        <a href="{{ route('supports.create', ['potential_target_id' => $selectedTarget['id']]) }}"
                                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                            지원 보고서 작성
                                        </a>
                                    @endif
                                @endcan
                            </div>
                        </div>
                        @if($selectedTarget['is_contract'] ?? false)
                            <p class="text-xs text-gray-500 mb-2">정식 기관(계약·SK 발급) 이후 작성 가능합니다. 저장 시 미팅 이력에도 반영될 수 있습니다.</p>
                        @endif
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="max-h-44 overflow-y-auto overflow-x-auto">
                                <table class="w-full text-xs whitespace-nowrap">
                                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                                        <tr class="text-gray-600">
                                            <th class="px-3 py-2 text-left">지원일</th>
                                            <th class="px-3 py-2 text-left">시간</th>
                                            <th class="px-3 py-2 text-left">담당자</th>
                                            <th class="px-3 py-2 text-left">지원방법</th>
                                            <th class="px-3 py-2 text-left">상태</th>
                                            <th class="px-3 py-2 text-left">소통내용</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($detailSupportRecords as $support)
                                            <tr>
                                                <td class="px-3 py-2">{{ $support['support_date'] }}</td>
                                                <td class="px-3 py-2">{{ $support['meet_time'] }}</td>
                                                <td class="px-3 py-2">{{ $support['tr_name'] }}</td>
                                                <td class="px-3 py-2">{{ $support['support_type'] }}</td>
                                                <td class="px-3 py-2">{{ $support['status'] }}</td>
                                                <td class="px-3 py-2 max-w-72 whitespace-normal break-words">{{ \Illuminate\Support\Str::limit($support['to_account'], 100) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-3 py-8 text-center text-gray-400">기관지원보고서 이력이 없습니다.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
