<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2" data-mochi-flash-dismiss="3000" role="status">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @error('authorization')
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-center gap-2" data-mochi-flash-dismiss="4000" role="alert">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            {{ $message }}
        </div>
    @enderror

    {{-- 상단 요약 영역 --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-green-700">잠재고객 리스트</h2>
            <span class="text-gray-300">|</span>
            <button wire:click="$set('summaryFilter', 'all')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $summaryFilter === 'all' ? 'font-semibold text-blue-700' : '' }}">
                전체 <span class="font-semibold text-blue-600">{{ $allCount }}</span>
            </button>
            <button wire:click="$set('summaryFilter', 'new')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $summaryFilter === 'new' ? 'font-semibold text-green-700' : '' }}">
                신규 <span class="font-semibold text-green-600">{{ $newCount }}</span>
            </button>
            <button wire:click="$set('summaryFilter', 'terminated')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $summaryFilter === 'terminated' ? 'font-semibold text-red-700' : '' }}">
                해지 <span class="font-semibold text-red-500">{{ $terminatedCount }}</span>
            </button>
            <div class="ml-auto text-gray-500">
                현재 조건 결과: <span class="font-semibold text-gray-700">{{ $totalCount }}</span>건
            </div>
        </div>
    </div>

    {{-- 필터 영역 --}}
    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="filterYear"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="">전체 연도</option>
                @foreach($yearList as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterManager"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="">전체 담당자</option>
                @foreach($managerList as $manager)
                    <option value="{{ $manager }}">{{ $manager }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterType"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="">전체 신규구분</option>
                @foreach($typeList as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>

            <input type="text"
                   wire:model.live.debounce.300ms="filterRegion"
                   placeholder="지역(주소) 검색"
                   class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />

            <div class="relative flex-1 min-w-56">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="기관명, 코드, 원장명, 주소 검색"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
            </div>

            <button type="button"
                    wire:click="openCreateModal"
                    class="px-4 py-2 text-sm font-medium text-white bg-mochi-header hover:bg-mochi-header/90 rounded-lg transition-colors cursor-pointer">
                신규 잠재 기관 등록
            </button>
        </div>
        <div class="flex flex-wrap items-center gap-3 pt-3 mt-3 border-t border-gray-200/80">
            <select wire:model.live="filterIntroductionPath"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="">전체 소개경로</option>
                <option value="__empty__">(미입력)</option>
                @foreach($introductionPathList as $path)
                    <option value="{{ $path }}">{{ $path }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterContractPossibility"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="">전체 계약가능성</option>
                <option value="contract">계약</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
                <option value="none">미지정</option>
            </select>
        </div>
    </div>

    {{-- 메인 리스트 테이블 (가로 스크롤 없이 뷰포트 너비에 맞춤: table-fixed + 줄바꿈/말줄임) --}}
    <div class="mochi-table-card">
        <div class="w-full min-w-0">
            <table class="w-full table-fixed border-collapse text-xs text-gray-700">
                <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="w-[3%] px-1.5 py-2 text-left text-[11px] font-semibold leading-tight">No</th>
                        <th class="w-[4%] px-1.5 py-2 text-left text-[11px] font-semibold leading-tight">ID</th>
                        <th class="w-[7%] min-w-0 px-1.5 py-2 text-left text-[11px] font-semibold leading-tight">담당자</th>
                        <th class="w-[7%] px-1.5 py-2 text-left text-[11px] font-semibold leading-tight">일자</th>
                        <th class="w-[8%] min-w-0 px-1.5 py-2 text-left text-[11px] font-semibold leading-tight">구분</th>
                        <th class="w-[4%] px-1.5 py-2 text-center text-[11px] font-semibold leading-tight">미팅</th>
                        <th class="w-[8%] min-w-0 px-1.5 py-2 text-left text-[11px] font-semibold leading-tight">컨설팅</th>
                        <th class="w-[20%] min-w-0 px-1.5 py-2 text-left text-[11px] font-semibold leading-tight">기관명</th>
                        <th class="w-[3%] px-1.5 py-2 text-center text-[11px] font-semibold leading-tight">LS</th>
                        <th class="w-[3.5%] px-0.5 py-2 text-center text-[10px] font-semibold leading-tight">GS<br>유</th>
                        <th class="w-[3.5%] px-0.5 py-2 text-center text-[10px] font-semibold leading-tight">GS<br>초</th>
                        <th class="w-[4%] px-1.5 py-2 text-center text-[11px] font-semibold leading-tight">합계</th>
                        <th class="w-[8%] min-w-0 px-1.5 py-2 text-left text-[11px] font-semibold leading-tight">소개</th>
                        <th class="w-[5%] px-1 py-2 text-center text-[10px] font-semibold leading-tight">가능</th>
                        <th class="w-[12%] px-1.5 py-2 text-center text-[11px] font-semibold leading-tight">액션</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($targets as $index => $target)
                        @php
                            $meetingCount = (int) ($meetingCountByAccount[$target->AccountName] ?? 0);
                            $manager = (string) ($target->AccountManager ?? '');
                            $type = (string) ($target->Type ?? '');
                            $gubun = (string) ($target->Gubun ?? '');
                            $accountName = (string) ($target->AccountName ?? '');
                            $connected = (string) ($target->Connected ?? '');
                        @endphp
                        <tr wire:key="potential-target-row-{{ $target->ID }}"
                            wire:click="openDetailModal({{ $target->ID }})"
                            class="mochi-table-row-hover transition-colors cursor-pointer align-top">
                            <td class="px-1.5 py-2 text-gray-500 tabular-nums">{{ $targets->firstItem() + $index }}</td>
                            <td class="px-1.5 py-2 tabular-nums">{{ $target->ID }}</td>
                            <td class="min-w-0 px-1.5 py-2">
                                <span class="line-clamp-2 break-words leading-snug" title="{{ $manager }}">{{ $manager !== '' ? $manager : '-' }}</span>
                            </td>
                            <td class="px-1.5 py-2 whitespace-nowrap tabular-nums">{{ $target->CreatedDate?->format('Y-m-d') ?? '-' }}</td>
                            <td class="min-w-0 px-1.5 py-2">
                                <span class="line-clamp-2 break-words leading-snug" title="{{ $type }}">{{ $type !== '' ? $type : '-' }}</span>
                            </td>
                            <td class="px-1.5 py-2 text-center tabular-nums">{{ $meetingCount }}</td>
                            <td class="min-w-0 px-1.5 py-2">
                                <span class="line-clamp-2 break-words leading-snug" title="{{ $gubun }}">{{ $gubun !== '' ? $gubun : '-' }}</span>
                            </td>
                            <td class="min-w-0 px-1.5 py-2 font-medium text-gray-900">
                                <span class="line-clamp-2 break-words leading-snug" title="{{ $accountName }}">{{ $accountName !== '' ? $accountName : '-' }}</span>
                            </td>
                            <td class="px-1.5 py-2 text-center tabular-nums">{{ $target->LS ?? 0 }}</td>
                            <td class="px-0.5 py-2 text-center tabular-nums">{{ $target->GS_K ?? 0 }}</td>
                            <td class="px-0.5 py-2 text-center tabular-nums">{{ $target->GS_E ?? 0 }}</td>
                            <td class="px-1.5 py-2 text-center font-semibold text-gray-800 tabular-nums">{{ $target->studentTotal() }}</td>
                            <td class="min-w-0 px-1.5 py-2 truncate" title="{{ $connected }}">{{ filled($target->Connected) ? $target->Connected : '-' }}</td>
                            <td class="px-1 py-2 text-center">
                                @if($target->IsContract)
                                    <span class="font-medium text-emerald-700">계약</span>
                                @else
                                    <span class="tabular-nums">{{ filled($target->Possibility) ? $target->Possibility : '-' }}</span>
                                @endif
                            </td>
                            <td class="px-1.5 py-2 text-center whitespace-nowrap">
                                @if($target->IsContract || $target->Possibility === '계약')
                                    <span class="text-[11px] text-gray-500" title="계약 완료 처리됨">완료</span>
                                @else
                                    <button type="button"
                                            wire:click.stop="openContractModal({{ $target->ID }})"
                                            title="계약 완료 처리"
                                            class="inline-flex items-center justify-center rounded-md bg-orange-500 px-2 py-1 text-[11px] font-medium text-white shadow-sm hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-1">
                                        계약완료
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-4 py-16 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="font-medium">잠재고객 데이터가 없습니다</p>
                                <p class="text-sm mt-1">필터 조건을 변경해 보세요.</p>
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

    {{-- 잠재 기관 신규 등록 모달 --}}
    @if($showCreateModal)
        <div class="mochi-modal-overlay" wire:click.self="closeCreateModal">
            <div class="mochi-modal-shell max-w-5xl h-[85vh] max-h-[85vh] flex flex-col" wire:click.stop>
                <x-admin.modal-header title="잠재 기관 등록" close-action="closeCreateModal" />

                <form wire:submit="saveNewTarget" class="flex-1 overflow-y-auto">
                    <div class="px-6 py-5 space-y-6">
                        @php
                            $previewTotal = max(0, (int) ($newLS !== '' && is_numeric($newLS) ? $newLS : 0))
                                + max(0, (int) ($newGSK !== '' && is_numeric($newGSK) ? $newGSK : 0))
                                + max(0, (int) ($newGSE !== '' && is_numeric($newGSE) ? $newGSE : 0));
                        @endphp

                        <section class="space-y-4">
                            <h3 class="text-base font-semibold text-gray-900">신규 기관</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">담당자</label>
                                    <input type="text" wire:model="newManager" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" placeholder="담당자명" />
                                    @error('newManager') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">컨설팅타입 <span class="text-red-500">*</span></label>
                                    <input type="text"
                                           list="potential-consulting-type-suggestions"
                                           wire:model="newConsultingType"
                                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"
                                           placeholder="직접 입력하거나 목록에서 선택" />
                                    <datalist id="potential-consulting-type-suggestions">
                                        <option value="신규기관방문"></option>
                                        <option value="신규(24년)"></option>
                                        <option value="신규(25년)"></option>
                                        <option value="신규(26년)"></option>
                                        <option value="해지방문"></option>
                                    </datalist>
                                    @error('newConsultingType') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">소개경로</label>
                                    <input type="text" wire:model="newConnected" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" placeholder="예: 지인 소개" />
                                    @error('newConnected') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">기관명 <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model="newAccountName" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" placeholder="기관명을 입력하세요" />
                                    @error('newAccountName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">원장명</label>
                                    <input type="text" wire:model="newDirector" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" placeholder="원장명" />
                                    @error('newDirector') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                                    <input type="text" wire:model="newPhone" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" placeholder="연락처" />
                                    @error('newPhone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">미팅일자 <span class="text-red-500">*</span></label>
                                        <input type="date" wire:model="newMeetingDate" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                        @error('newMeetingDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">시작시간</label>
                                        <input type="time" wire:model="newMeetingTime" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                        @error('newMeetingTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">신규구분 <span class="text-red-500">*</span></label>
                                    <select wire:model="newType" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                        <option value="">선택</option>
                                        <option value="신규(24년)">신규(24년)</option>
                                        <option value="신규(25년)">신규(25년)</option>
                                        <option value="해지">해지</option>
                                    </select>
                                    @error('newType') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">가능성정도</label>
                                    <select wire:model="newPossibility" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                        <option value="">선택</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                    @error('newPossibility') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                                    <input type="text" wire:model="newAddress" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" placeholder="기관 주소" />
                                    @error('newAddress') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                                     SK코드는 계약 완료시 생성됩니다.
                                </p>
                                @if(config('potential_institutions.show_support_report_ui'))
                                    <p class="text-xs text-gray-600">
                                        이 모달 아래쪽(미팅내용 다음)에 <span class="font-medium text-gray-800">기관 지원 보고서(선택)</span> 항목이 있습니다. 「같이 등록」을 켜면 저장과 동시에 보고서 한 건이 생성됩니다.
                                    </p>
                                @endif
                            </div>
                        </section>

                        <section class="space-y-4 border-t border-gray-200 pt-5">
                            <h3 class="text-base font-semibold text-gray-900">인원 정보</h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">LittleSEED</label>
                                    <input type="number" min="0" wire:model.live.debounce.200ms="newLS" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                    @error('newLS') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">GrapeSEED(유)</label>
                                    <input type="number" min="0" wire:model.live.debounce.200ms="newGSK" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                    @error('newGSK') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">GrapeSEED(초)</label>
                                    <input type="number" min="0" wire:model.live.debounce.200ms="newGSE" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                    @error('newGSE') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">합계</label>
                                    <div class="flex min-h-[42px] items-center rounded-lg border border-gray-200 bg-gray-100 px-3 text-sm font-medium text-gray-800 tabular-nums">
                                        {{ number_format($previewTotal) }}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="space-y-3 border-t border-gray-200 pt-5">
                            <h3 class="text-base font-semibold text-gray-900">미팅내용</h3>
                            <textarea wire:model="newDescription" rows="6" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header resize-y" placeholder="미팅/컨설팅 내용을 입력하세요"></textarea>
                            @error('newDescription') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </section>

                        @if(config('potential_institutions.show_support_report_ui'))
                        <section class="space-y-4 border-t border-gray-200 pt-5">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <h3 class="text-base font-semibold text-gray-900">기관 지원 보고서 <span class="text-sm font-normal text-gray-500">(선택)</span></h3>
                                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                    <input type="checkbox" wire:model.live="newIncludeSupportReport" class="rounded border-gray-300 text-blue-600 focus:ring-mochi-header" />
                                    <span class="text-sm text-gray-700">같이 등록</span>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500">체크하면 저장과 동시에 기관 지원 보고서 목록에 한 건이 추가됩니다. SK 발급 전에는 첨부 파일 업로드는 「CO 기관지원보고서 작성」 화면에서만 할 수 있습니다.</p>

                            @if($newIncludeSupportReport)
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">지원 날짜 <span class="text-red-500">*</span></label>
                                        <input type="date" wire:model="newSupportReportDate" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                        @error('newSupportReportDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">지원 시간 <span class="text-red-500">*</span></label>
                                        <input type="time" wire:model="newSupportReportTime" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                        @error('newSupportReportTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">지원 방법 <span class="text-red-500">*</span></label>
                                        <select wire:model="newSupportReportType" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                            <option>전화</option>
                                            <option>대면</option>
                                            <option>화상</option>
                                            <option>이메일</option>
                                            <option>문자</option>
                                            <option>기타</option>
                                        </select>
                                        @error('newSupportReportType') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">CO명</label>
                                        <input type="text" wire:model="newSupportReportTrName" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" placeholder="보고서 담당 표기명" />
                                        @error('newSupportReportTrName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">참석자</label>
                                        <input type="text" wire:model="newSupportReportTarget" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" placeholder="예: 원장, 교사 2명" />
                                        @error('newSupportReportTarget') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">기관과의 소통내용</label>
                                        <textarea wire:model="newSupportReportToAccount" rows="5" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header resize-y"></textarea>
                                        @error('newSupportReportToAccount') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">본사/타 부서 공유 내용</label>
                                        <textarea wire:model="newSupportReportToDepart" rows="3" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header resize-y"></textarea>
                                        @error('newSupportReportToDepart') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                    <input type="checkbox" wire:model="newSupportReportCompleted" class="rounded border-gray-300 text-blue-600 focus:ring-mochi-header" />
                                    <span class="text-sm text-gray-700">완료 처리</span>
                                </label>
                            @endif
                        </section>
                        @endif

                        <section class="space-y-4 border-t border-gray-200 pt-5">
                            <h3 class="text-base font-semibold text-gray-900">고객관리(횟수)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">관계형성</label>
                                    <input type="number" min="0" wire:model="newApproaching" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                    @error('newApproaching') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">제품소개</label>
                                    <input type="number" min="0" wire:model="newPresenting" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                    @error('newPresenting') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">상담/조정</label>
                                    <input type="number" min="0" wire:model="newConsultingCount" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                    @error('newConsultingCount') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">도입제안</label>
                                    <input type="number" min="0" wire:model="newClosing" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                    @error('newClosing') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">도입취소</label>
                                    <input type="number" min="0" wire:model="newDroppedOut" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                    @error('newDroppedOut') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </section>

                        @error('createForm')
                            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-2">
                        <button type="button" wire:click="closeCreateModal"
                                class="px-5 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                            취소하기
                        </button>
                        <button type="submit"
                                class="px-5 py-2 text-sm font-medium bg-mochi-header hover:bg-mochi-header/90 text-white rounded-lg transition-colors cursor-pointer"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-70 cursor-not-allowed"
                                wire:target="saveNewTarget">
                            <span wire:loading.remove wire:target="saveNewTarget">저장하기</span>
                            <span wire:loading wire:target="saveNewTarget">저장 중...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- 잠재고객 상세 모달 --}}
    @if($showDetailModal && $selectedTarget)
        <div class="mochi-modal-overlay"
             wire:click.self="closeDetailModal">
            <div class="mochi-modal-shell max-w-4xl h-[80vh] max-h-[80vh] flex flex-col"
                 wire:click.stop>
                <x-admin.modal-header
                    title="잠재고객 상세 정보"
                    :subtitle="($selectedTarget['account_name'] ?? '-').' (ID: '.($selectedTarget['id'] ?? '-').')'"
                    close-action="closeDetailModal"
                />

                <div class="px-6 py-5 text-sm flex-1 overflow-y-auto">
                    <x-potential-institution.detail-summary
                        :selected-target="$selectedTarget"
                        :detail-edit-mode="$detailEditMode"
                        :show-meeting-count="true"
                        :contract-editable-separately="true"
                        :edit-l-s="$editLS"
                        :edit-g-s-k="$editGSK"
                        :edit-g-s-e="$editGSE"
                    />

                    @if (! $detailEditMode)
                        <div class="border border-gray-200 rounded-lg overflow-hidden mb-4">
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    <tr>
                                        <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">계약여부</th>
                                        <td class="px-3 py-2" wire:click.stop @if($selectedTarget['is_contract'] ?? false) colspan="3" @endif>
                                            <select wire:model="detailModalContract"
                                                    wire:change="requestContractChange"
                                                    class="w-full max-w-[11rem] py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                                <option value="0">미계약</option>
                                                <option value="1">계약</option>
                                            </select>
                                        </td>
                                        @if(!($selectedTarget['is_contract'] ?? false))
                                        <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">SK CODE</th>
                                        <td class="px-3 py-2" wire:click.stop>
                                            <input type="text"
                                                   wire:model.defer="detailModalSkCode"
                                                   placeholder="계약 처리 시 임시 SK CODE (자동발급)"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            <p class="mt-1 text-xs text-gray-400"> LEAD-xxx 임시 코드가 발급됩니다.</p>
                                        </td>
                                        @endif
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if(!($selectedTarget['is_contract'] ?? false))
                        @can('managePotentialInstitutions')
                            <div class="mb-4">
                                <livewire:potential-institution-meeting-form
                                    :co-new-target-id="$selectedTarget['id']"
                                    :wire:key="'pim-list-'.$selectedTarget['id']"
                                />
                            </div>
                        @endcan
                    @endif

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-base font-bold text-[#1f4f8f]">미팅/컨설팅 이력</h3>
                            <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                                총 {{ count($detailMeetings) }}건
                            </span>
                        </div>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="max-h-56 overflow-y-auto overflow-x-auto">
                                <table class="w-full text-xs whitespace-nowrap">
                                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                                        <tr class="text-gray-600">
                                            <th class="px-3 py-2 text-left">일자</th>
                                            <th class="px-3 py-2 text-left">시간</th>
                                            <th class="px-3 py-2 text-left">담당자</th>
                                            <th class="px-3 py-2 text-left">컨설팅타입</th>
                                            <th class="px-3 py-2 text-left">가능성</th>
                                            <th class="px-3 py-2 text-left">미팅내용</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($detailMeetings as $meeting)
                                            <tr wire:key="meeting-row-{{ $meeting['id'] }}"
                                                wire:click="openMeetingDetailModal({{ $meeting['id'] }})"
                                                class="hover:bg-blue-50 cursor-pointer transition-colors">
                                                <td class="px-3 py-2">{{ $meeting['meeting_date'] }}</td>
                                                <td class="px-3 py-2">{{ $meeting['meeting_time'] }} ~ {{ $meeting['meeting_time_end'] }}</td>
                                                <td class="px-3 py-2">{{ $meeting['account_manager'] }}</td>
                                                <td class="px-3 py-2">{{ $meeting['consulting_type'] }}</td>
                                                <td class="px-3 py-2">{{ $meeting['possibility'] }}</td>
                                                <td class="px-3 py-2 max-w-80 whitespace-normal break-words">
                                                    {{ \Illuminate\Support\Str::limit($meeting['description'], 120) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-3 py-8 text-center text-gray-400">
                                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    미팅/컨설팅 이력이 없습니다.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @if(config('potential_institutions.show_support_report_ui'))
                    <div class="mt-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <h3 class="text-base font-bold text-[#1f4f8f]">기관지원보고서 이력</h3>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                                    총 {{ count($detailSupportRecords) }}건
                                </span>
                                @can('managePotentialInstitutions')
                                    @if(!($selectedTarget['is_contract'] ?? false))
                                        <a href="{{ route('supports.create', ['potential_target_id' => $selectedTarget['id']]) }}"
                                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-mochi-header rounded-lg hover:bg-mochi-header/90">
                                            지원 보고서 작성
                                        </a>
                                    @endif
                                @endcan
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">작성 화면으로 이동합니다. 저장 시 미팅 이력에도 반영될 수 있습니다.</p>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="max-h-56 overflow-y-auto overflow-x-auto">
                                <table class="w-full text-xs whitespace-nowrap">
                                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                                        <tr class="text-gray-600">
                                            <th class="px-3 py-2 text-left">지원일</th>
                                            <th class="px-3 py-2 text-left">시간</th>
                                            <th class="px-3 py-2 text-left">담당자</th>
                                            <th class="px-3 py-2 text-left">지원방법</th>
                                            <th class="px-3 py-2 text-left">참석자</th>
                                            <th class="px-3 py-2 text-left">상태</th>
                                            <th class="px-3 py-2 text-left">기관과의 소통내용</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($detailSupportRecords as $supportRecord)
                                            <tr wire:key="support-row-{{ $supportRecord['id'] }}"
                                                wire:click="openSupportDetailModal({{ $supportRecord['id'] }})"
                                                class="hover:bg-blue-50 transition-colors cursor-pointer">
                                                <td class="px-3 py-2">{{ $supportRecord['support_date'] }}</td>
                                                <td class="px-3 py-2">{{ $supportRecord['meet_time'] }}</td>
                                                <td class="px-3 py-2">{{ $supportRecord['tr_name'] }}</td>
                                                <td class="px-3 py-2">{{ $supportRecord['support_type'] }}</td>
                                                <td class="px-3 py-2">{{ $supportRecord['target'] }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="text-[10px] {{ $supportRecord['completed'] ? 'text-green-700' : 'text-gray-600' }}">
                                                        {{ $supportRecord['status'] }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 max-w-80 whitespace-normal break-words">
                                                    {{ \Illuminate\Support\Str::limit($supportRecord['to_account'], 120) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-3 py-8 text-center text-gray-400">
                                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    작성된 기관지원보고서가 없습니다.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- 기관지원보고서 상세 모달 --}}
    @if(config('potential_institutions.show_support_report_ui') && $showSupportDetailModal && $selectedSupportRecord)
        <div class="mochi-modal-overlay z-[60]" wire:click.self="closeSupportDetailModal">
            <div class="mochi-modal-shell max-w-3xl h-[70vh] max-h-[70vh] flex flex-col" wire:click.stop>
                <x-admin.modal-header
                    title="기관지원보고서 상세"
                    :subtitle="($selectedTarget['account_name'] ?? '-').' · '.($selectedSupportRecord['support_date'] ?? '-')"
                    close-action="closeSupportDetailModal"
                />

                <div class="px-6 py-5 flex-1 overflow-y-auto">
                    <div class="border border-gray-200 rounded-lg overflow-hidden mb-4">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">지원일</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedSupportRecord['support_date'] ?? '-' }}</td>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">시간</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedSupportRecord['meet_time'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당자</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedSupportRecord['tr_name'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">지원방법</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedSupportRecord['support_type'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">참석자</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedSupportRecord['target'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">상태</th>
                                    <td class="px-3 py-2">
                                        <span class="text-[10px] {{ ($selectedSupportRecord['completed'] ?? false) ? 'text-green-700' : 'text-gray-600' }}">
                                            {{ $selectedSupportRecord['status'] ?? '-' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">기관과의 소통내용</h4>
                        <div class="mochi-multiline-readout rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 leading-6 min-h-[120px]">{{ $selectedSupportRecord['to_account'] ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 미팅/컨설팅 상세 모달 --}}
    <x-potential-institution.meeting-detail-modal
        :show="$showMeetingDetailModal"
        :selected-meeting="$selectedMeeting"
        :selected-target="$selectedTarget"
        :meeting-detail-edit-mode="$meetingDetailEditMode"
        delete-policy="admin"
    />

    {{-- 상세 모달 계약 변경 확인 모달 --}}
    @if($showContractChangeConfirmModal)
        <div class="mochi-modal-overlay" wire:click.self="cancelContractChange">
            <div class="mochi-modal-shell max-w-md flex flex-col" wire:click.stop>
                <x-admin.modal-header
                    :title="$pendingContractChange ? '계약 전환 확인' : '미계약 전환 확인'"
                    close-action="cancelContractChange"
                />
                <div class="px-6 py-5 space-y-3">
                    <p class="text-sm font-semibold text-gray-900">{{ $pendingContractChangeName ?: '-' }}</p>
                    @if($pendingContractChange)
                        <p class="text-sm text-gray-600">
                            이 잠재기관을 계약으로 변경합니다. 처리 후 기관 리스트에 반영됩니다.
                        </p>
                    @else
                        <p class="text-sm text-gray-600">
                            이 기관을 미계약으로 전환합니다. 처리 후 기관 리스트에서는 숨김 처리되고 잠재기관에서 다시 관리할 수 있습니다.
                        </p>
                    @endif
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button"
                            wire:click="cancelContractChange"
                            class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                        취소
                    </button>
                    <button type="button"
                            wire:click="confirmContractChange"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="confirmContractChange"
                            class="px-4 py-2 text-sm font-medium text-white bg-mochi-header hover:bg-mochi-header/90 rounded-lg transition-colors cursor-pointer">
                        <span wire:loading.remove wire:target="confirmContractChange">
                            {{ $pendingContractChange ? '계약으로 변경' : '미계약으로 전환' }}
                        </span>
                        <span wire:loading wire:target="confirmContractChange">처리 중...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- 계약완료 확인 미니 모달 --}}
    @if($showContractModal)
        <div class="mochi-modal-overlay" wire:click.self="closeContractModal">
            <div class="mochi-modal-shell max-w-md flex flex-col" wire:click.stop>
                <x-admin.modal-header title="계약 완료 처리" close-action="closeContractModal" />
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500">계약 처리 대상</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">{{ $pendingContractName ?: '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            SK CODE <span class="text-gray-400 font-normal">(선택)</span>
                        </label>
                        <input type="text"
                               wire:model.defer="contractSkCode"
                               placeholder="예: ABC-001 — 비워두면 자동 발급"
                               class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400" />
                        <p class="mt-1.5 text-xs text-gray-500">
                            임시 코드(LEAD-xxx)가 자동 발급되고,
                            E-ordering에서 확정 SK를 입력하면 5분 이내 자동 입력됩니다.
                        </p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button"
                            wire:click="closeContractModal"
                            class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                        취소
                    </button>
                    <button type="button"
                            wire:click="markContractComplete({{ $pendingContractId }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="markContractComplete"
                            class="px-4 py-2 text-sm font-medium text-white bg-orange-500 hover:bg-orange-600 rounded-lg transition-colors cursor-pointer">
                        <span wire:loading.remove wire:target="markContractComplete">계약 완료 처리</span>
                        <span wire:loading wire:target="markContractComplete">처리 중...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div wire:loading.delay class="fixed bottom-6 right-6 z-50">
        <div class="bg-white rounded-xl px-4 py-3 shadow-lg border border-gray-200 flex items-center gap-2 text-sm text-gray-700">
            <svg class="animate-spin w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            로딩 중...
        </div>
    </div>
</div>

