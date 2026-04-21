<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

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
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 연도</option>
                @foreach($yearList as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterManager"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 담당자</option>
                @foreach($managerList as $manager)
                    <option value="{{ $manager }}">{{ $manager }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterType"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 신규구분</option>
                @foreach($typeList as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>

            <input type="text"
                   wire:model.live.debounce.300ms="filterRegion"
                   placeholder="지역(주소) 검색"
                   class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />

            <div class="relative flex-1 min-w-56">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="기관명, 코드, 원장명, 주소 검색"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <a href="{{ route('institutions.create') }}"
               class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                신규 기관 등록
            </a>
        </div>
        <div class="flex flex-wrap items-center gap-3 pt-3 mt-3 border-t border-gray-200/80">
            <select wire:model.live="filterIntroductionPath"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 소개경로</option>
                <option value="__empty__">(미입력)</option>
                @foreach($introductionPathList as $path)
                    <option value="{{ $path }}">{{ $path }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterContractPossibility"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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

    {{-- 메인 리스트 테이블 --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="px-3 py-2 text-left text-xs font-semibold">No</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">ID</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">담당자</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">일자</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">신규구분</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">미팅횟수</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">컨설팅타입</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">기관명</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">LS</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">GS(유)</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">GS(초)</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">합계</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">소개경로</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">계약가능성</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">액션</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($targets as $index => $target)
                        @php
                            $meetingCount = (int) ($meetingCountByAccount[$target->AccountName] ?? 0);
                        @endphp
                        <tr wire:key="potential-target-row-{{ $target->ID }}"
                            wire:click="openDetailModal({{ $target->ID }})"
                            class="mochi-table-row-hover transition-colors cursor-pointer">
                            <td class="px-3 py-2 text-gray-500 text-xs">{{ $targets->firstItem() + $index }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $target->ID }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $target->AccountManager ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $target->CreatedDate?->format('Y-m-d') ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $target->Type ?? '-' }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $meetingCount }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $target->Gubun ?? '-' }}</td>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ $target->AccountName ?? '-' }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $target->LS ?? 0 }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $target->GS_K ?? 0 }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $target->GS_E ?? 0 }}</td>
                            <td class="px-3 py-2 text-center font-semibold text-gray-800">{{ $target->Total ?? 0 }}</td>
                            <td class="px-3 py-2 text-gray-700 max-w-[14rem] truncate" title="{{ $target->Connected ?? '' }}">{{ filled($target->Connected) ? $target->Connected : '-' }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">
                                @if($target->IsContract)
                                    <span class="font-medium text-emerald-700">계약</span>
                                @else
                                    {{ filled($target->Possibility) ? $target->Possibility : '-' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($target->IsContract)
                                    <span class="text-xs text-gray-500">계약완료됨</span>
                                @else
                                    <button type="button"
                                            wire:click.stop="markContractComplete({{ $target->ID }})"
                                            class="inline-flex items-center justify-center rounded-lg bg-orange-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-1">
                                        계약완료
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-4 py-16 text-center text-gray-400">
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
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50/80 to-white">
                    <h2 class="text-xl font-semibold text-gray-900">잠재 기관 등록</h2>
                    <button wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveNewTarget" class="flex-1 overflow-y-auto">
                    <div class="px-6 py-5 space-y-6">
                        @php
                            $previewTotal = max(0, (int) ($newLS ?? 0))
                                + max(0, (int) ($newGSK ?? 0))
                                + max(0, (int) ($newGSE ?? 0));
                        @endphp

                        <section class="space-y-4">
                            <h3 class="text-base font-semibold text-gray-900">신규 기관</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">담당자</label>
                                    <input type="text" wire:model="newManager" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="담당자명" />
                                    @error('newManager') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">컨설팅타입 <span class="text-red-500">*</span></label>
                                    <input type="text"
                                           list="potential-consulting-type-suggestions"
                                           wire:model="newConsultingType"
                                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="직접 입력하거나 목록에서 선택" />
                                    <datalist id="potential-consulting-type-suggestions">
                                        <option value="신규기관방문"></option>
                                        <option value="신규(24년)"></option>
                                        <option value="신규(25년)"></option>
                                        <option value="해지방문"></option>
                                    </datalist>
                                    @error('newConsultingType') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">소개경로</label>
                                    <input type="text" wire:model="newConnected" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="예: 지인 소개" />
                                    @error('newConnected') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">기관명 <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model="newAccountName" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="기관명을 입력하세요" />
                                    @error('newAccountName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">원장명</label>
                                    <input type="text" wire:model="newDirector" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="원장명" />
                                    @error('newDirector') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                                    <input type="text" wire:model="newPhone" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="연락처" />
                                    @error('newPhone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">미팅일자 <span class="text-red-500">*</span></label>
                                        <input type="date" wire:model="newMeetingDate" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                        @error('newMeetingDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">시작시간</label>
                                        <input type="time" wire:model="newMeetingTime" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                        @error('newMeetingTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">신규구분 <span class="text-red-500">*</span></label>
                                    <select wire:model="newType" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">선택</option>
                                        <option value="신규(24년)">신규(24년)</option>
                                        <option value="신규(25년)">신규(25년)</option>
                                        <option value="해지">해지</option>
                                    </select>
                                    @error('newType') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">가능성정도</label>
                                    <select wire:model="newPossibility" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">선택</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                    @error('newPossibility') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                                <input type="text" wire:model="newAddress" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="기관 주소" />
                                @error('newAddress') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <p class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                                    신규 등록 단계에서는 SK코드를 발급하지 않습니다. 계약 처리 시 SK코드가 자동 발급되어 기관리스트에 반영됩니다.
                                </p>
                            </div>
                        </section>

                        <section class="space-y-3 border-t border-gray-200 pt-5">
                            <h3 class="text-base font-semibold text-gray-900">미팅내용</h3>
                            <textarea wire:model="newDescription" rows="6" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y" placeholder="미팅/컨설팅 내용을 입력하세요"></textarea>
                            @error('newDescription') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </section>

                        <section class="space-y-4 border-t border-gray-200 pt-5">
                            <h3 class="text-base font-semibold text-gray-900">인원 정보</h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">LittleSEED</label>
                                    <input type="number" min="0" wire:model="newLS" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    @error('newLS') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">GrapeSEED(유)</label>
                                    <input type="number" min="0" wire:model="newGSK" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    @error('newGSK') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">GrapeSEED(초)</label>
                                    <input type="number" min="0" wire:model="newGSE" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    @error('newGSE') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">합계</label>
                                    <input type="text" value="{{ $previewTotal }}" readonly class="w-full py-2 px-3 text-sm border border-gray-200 bg-gray-100 text-gray-700 rounded-lg" />
                                </div>
                            </div>
                        </section>

                        <section class="space-y-4 border-t border-gray-200 pt-5">
                            <h3 class="text-base font-semibold text-gray-900">고객관리(횟수)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">관계형성</label>
                                    <input type="number" min="0" wire:model="newApproaching" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    @error('newApproaching') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">제품소개</label>
                                    <input type="number" min="0" wire:model="newPresenting" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    @error('newPresenting') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">상담/조정</label>
                                    <input type="number" min="0" wire:model="newConsultingCount" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    @error('newConsultingCount') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">도입제안</label>
                                    <input type="number" min="0" wire:model="newClosing" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    @error('newClosing') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">도입취소</label>
                                    <input type="number" min="0" wire:model="newDroppedOut" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                class="px-5 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors cursor-pointer"
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
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50/80 to-white">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">잠재고객 상세 정보</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $selectedTarget['account_name'] ?? '-' }} (ID: {{ $selectedTarget['id'] ?? '-' }})
                        </p>
                    </div>
                    <button wire:click="closeDetailModal" class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 text-sm flex-1 overflow-y-auto">
                    <div class="border border-gray-200 rounded-lg overflow-hidden mb-4">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당자</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['account_manager'] ?? '-' }}</td>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">일자</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['created_date'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">신규구분</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['type'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">컨설팅타입</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['gubun'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관명</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['account_name'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">코드</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['account_code'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">LS / GS(유) / GS(초)</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        {{ $selectedTarget['ls'] ?? 0 }} / {{ $selectedTarget['gs_k'] ?? 0 }} / {{ $selectedTarget['gs_e'] ?? 0 }}
                                    </td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">합계</th>
                                    <td class="px-3 py-2 font-semibold text-gray-900">{{ $selectedTarget['total'] ?? 0 }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">미팅횟수</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['meeting_count'] ?? 0 }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">계약여부</th>
                                    <td class="px-3 py-2" wire:click.stop>
                                        <select wire:model="detailModalContract"
                                                wire:change="commitDetailContract"
                                                class="w-full max-w-[11rem] py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="0">미계약</option>
                                            <option value="1">계약</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">주소</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['address'] ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

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
                                                    미팅/컨설팅 이력이 없습니다.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-base font-bold text-[#1f4f8f]">기관지원보고서 이력</h3>
                            <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                                총 {{ count($detailSupportRecords) }}건
                            </span>
                        </div>
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
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold
                                                        {{ $supportRecord['completed'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
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
                                                    작성된 기관지원보고서가 없습니다.
                                                </td>
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

    {{-- 기관지원보고서 상세 모달 --}}
    @if($showSupportDetailModal && $selectedSupportRecord)
        <div class="mochi-modal-overlay z-[60]" wire:click.self="closeSupportDetailModal">
            <div class="mochi-modal-shell max-w-3xl h-[70vh] max-h-[70vh] flex flex-col" wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50/80 to-white">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">기관지원보고서 상세</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $selectedTarget['account_name'] ?? '-' }} · {{ $selectedSupportRecord['support_date'] ?? '-' }}
                        </p>
                    </div>
                    <button wire:click="closeSupportDetailModal" class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

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
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold
                                            {{ ($selectedSupportRecord['completed'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ $selectedSupportRecord['status'] ?? '-' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">기관과의 소통내용</h4>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 leading-6 whitespace-pre-wrap break-words min-h-[120px]">
                            {{ $selectedSupportRecord['to_account'] ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 미팅/컨설팅 상세 모달 --}}
    @if($showMeetingDetailModal && $selectedMeeting)
        <div class="mochi-modal-overlay z-[60]" wire:click.self="closeMeetingDetailModal">
            <div class="mochi-modal-shell max-w-3xl h-[70vh] max-h-[70vh] flex flex-col" wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50/80 to-white">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">미팅/컨설팅 상세</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $selectedMeeting['account_name'] ?? '-' }} · {{ $selectedMeeting['meeting_date'] ?? '-' }}
                        </p>
                    </div>
                    <button wire:click="closeMeetingDetailModal" class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 flex-1 overflow-y-auto">
                    <div class="border border-gray-200 rounded-lg overflow-hidden mb-4">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관명</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['account_name'] ?? '-' }}</td>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당자</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['account_manager'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">일자</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['meeting_date'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">시간</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        {{ $selectedMeeting['meeting_time'] ?? '-' }} ~ {{ $selectedMeeting['meeting_time_end'] ?? '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">컨설팅타입</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['consulting_type'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">가능성</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['possibility'] ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">미팅내용</h4>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 leading-6 whitespace-pre-wrap break-words">
                            {{ $selectedMeeting['description'] ?? '-' }}
                        </div>
                    </div>
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

