<div class="mochi-page">

    {{-- ───── 성공 메시지 ───── --}}
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- ───── 상단: 필터 + 버튼 영역 ───── --}}
    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">

            {{-- 년도 선택 --}}
            <select wire:model.live="filterYear"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 년도</option>
                @foreach($years as $year)
                    <option value="{{ $year }}">{{ $year }}년</option>
                @endforeach
            </select>

            {{-- 담당자 필터 --}}
            <select wire:model.live="filterTr"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 담당</option>
                @foreach($trList as $tr)
                    <option value="{{ $tr }}">{{ $tr }}</option>
                @endforeach
            </select>

            {{-- 기관 필터 --}}
            <select wire:model.live="filterSkCode"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 기관</option>
                @foreach($institutions as $inst)
                    <option value="{{ $inst->SKcode }}">[{{ $inst->SKcode }}] {{ $inst->AccountName }}</option>
                @endforeach
            </select>

            {{-- 키워드 검색 --}}
            <div class="relative flex-1 min-w-48">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="기관명, 이슈, 소통내용 검색..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
            </div>

            {{-- 건수 --}}
            <span class="text-sm text-gray-500">
                총 <span class="font-semibold text-blue-600">{{ $records->total() }}</span>건
            </span>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <a href="/supports/create"
                   class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700
                          text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    기관지원 보고서 작성
                </a>

                <button type="button"
                        wire:click="openContractUploadModal"
                        class="flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-800
                               text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    CO 파일업로드
                </button>
            </div>

        </div>
    </div>

    {{-- ───── 데이터 테이블 ───── --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">

                <thead class="mochi-table-head">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">SK코드</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">기관명</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">담당자</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">지원일</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">시간</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">지원방법</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">참석자</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">이슈</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase max-w-64">기관과의 소통내용</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase">상태</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase">완료처리</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase">수정</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                @forelse($records as $index => $record)
                    <tr wire:key="support-row-{{ $record->ID }}"
                        wire:click="openEditModal({{ $record->ID }})"
                        class="cursor-pointer mochi-table-row-hover transition-colors
                               {{ $record->CompletedDate ? 'opacity-70' : '' }}">

                        {{-- No --}}
                        <td class="px-3 py-2.5 text-gray-400 text-xs">
                            {{ $records->firstItem() + $index }}
                        </td>

                        {{-- SK코드 --}}
                        <td class="px-3 py-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                {{ $record->SK_Code ?? '-' }}
                            </span>
                        </td>

                        {{-- 기관명 --}}
                        <td class="px-3 py-2.5 font-medium text-gray-900 max-w-40 truncate" title="{{ $record->Account_Name }}">
                            {{ $record->Account_Name ?? '-' }}
                        </td>

                        {{-- 담당자 --}}
                        <td class="px-3 py-2.5 text-gray-600 text-xs">
                            {{ $record->TR_Name ?? '-' }}
                        </td>

                        {{-- 지원일 --}}
                        <td class="px-3 py-2.5 text-gray-700">
                            {{ $record->Support_Date?->format('Y-m-d') ?? '-' }}
                        </td>

                        {{-- 시간 --}}
                        <td class="px-3 py-2.5 text-gray-600 text-xs">
                            {{ $record->Meet_Time ? substr($record->Meet_Time, 0, 5) : '-' }}
                        </td>

                        {{-- 지원방법 --}}
                        <td class="px-3 py-2.5">
                            @php
                                $typeColor = match($record->Support_Type) {
                                    '대면'   => 'bg-orange-100 text-orange-700',
                                    '전화'   => 'bg-sky-100 text-sky-700',
                                    '화상'   => 'bg-violet-100 text-violet-700',
                                    default  => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            @if($record->Support_Type)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $typeColor }}">
                                    {{ $record->Support_Type }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- 참석자 --}}
                        <td class="px-3 py-2.5 text-gray-600 text-xs max-w-32 truncate" title="{{ $record->Target }}">
                            {{ $record->Target ?? '-' }}
                        </td>

                        {{-- 이슈 --}}
                        <td class="px-3 py-2.5 text-gray-600 text-xs max-w-40 truncate" title="{{ $record->Issue }}">
                            {{ $record->Issue ?? '-' }}
                        </td>

                        {{-- 기관과의 소통내용 --}}
                        <td class="px-3 py-2.5 text-gray-500 text-xs max-w-64">
                            <div class="truncate" title="{{ $record->TO_Account }}">
                                {{ $record->TO_Account ?? '-' }}
                            </div>
                        </td>

                        {{-- 상태 배지 --}}
                        <td class="px-3 py-2.5 text-center">
                            @if($record->CompletedDate)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    완료
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                    진행중
                                </span>
                            @endif
                        </td>

                        {{-- 완료처리 토글: wire:click.stop으로 행 클릭 이벤트 차단 --}}
                        <td class="px-3 py-2.5 text-center">
                            <button wire:click.stop="toggleComplete({{ $record->ID }})"
                                    class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent
                                           transition-colors duration-200 focus:outline-none
                                           {{ $record->CompletedDate ? 'bg-green-500' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200
                                             {{ $record->CompletedDate ? 'translate-x-4' : 'translate-x-0' }}">
                                </span>
                            </button>
                        </td>

                        {{-- 수정 버튼 --}}
                        <td class="px-3 py-2.5 text-center">
                            <button wire:click.stop="openEditModal({{ $record->ID }})"
                                    class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="px-4 py-16 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="font-medium">지원 내역이 없습니다</p>
                            <p class="text-sm mt-1 text-gray-400">우측 상단 버튼으로 첫 보고서를 작성해 보세요</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $records->links() }}
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         기관 지원 보고서 작성 모달
    ═══════════════════════════════════════════════════════════════ --}}
    @if($showModal)
        <div class="mochi-modal-overlay"
             wire:click.self="closeModal">
            <div class="mochi-modal-shell max-w-2xl flex flex-col max-h-[90vh]"
                 wire:click.stop>

                {{-- 모달 헤더 --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">기관 지원 내역 수정</h2>
                        <p class="text-xs text-gray-400 mt-0.5">기관 지원 보고서</p>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- 모달 폼 (스크롤 가능) --}}
                <form wire:submit="save" class="flex-1 overflow-y-auto">
                    @php
                        // 기관 선택 전에는 나머지 입력을 막아 실수를 줄입니다.
                        $institutionSelected = filled($formSkCode);
                    @endphp
                    <div class="px-6 py-5 space-y-5">

                        {{-- 섹션 1: 기본 정보 --}}
                        <div class="grid grid-cols-2 gap-4">

                            {{-- 기관명 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    기관명 <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       wire:model.live.debounce.200ms="formInstitutionKeyword"
                                       placeholder="기관명을 입력하세요 (예: 분당)"
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                              {{ $errors->has('formSkCode') ? 'border-red-400' : 'border-gray-300' }}" />

                                @if(filled($formInstitutionKeyword) && blank($formSkCode) && $institutionSuggestions->isNotEmpty())
                                    <div class="mt-2 max-h-44 overflow-auto border border-gray-200 rounded-lg bg-white shadow-sm">
                                        @foreach($institutionSuggestions as $inst)
                                            <button type="button"
                                                    wire:click="selectInstitution('{{ $inst->SKcode }}')"
                                                    class="w-full px-3 py-2 text-left text-sm hover:bg-blue-50 transition-colors">
                                                <span class="font-medium text-gray-900">{{ $inst->AccountName }}</span>
                                                <span class="ml-2 text-xs text-gray-500">({{ $inst->SKcode }})</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif

                                @if(filled($formSkCode))
                                    <p class="mt-1 text-xs text-blue-600">
                                        선택된 기관: {{ $formAccountName }} ({{ $formSkCode }})
                                    </p>
                                @endif

                                @error('formSkCode')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                                @unless($institutionSelected)
                                    <p class="mt-1 text-xs text-gray-500">기관을 먼저 선택하면 아래 입력 항목이 활성화됩니다.</p>
                                @endunless
                            </div>

                            {{-- CO명 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CO명</label>
                                <input type="text"
                                       wire:model="formCoName"
                                       @disabled(!$institutionSelected)
                                       class="w-full py-2 px-3 text-sm border rounded-lg
                                              {{ $institutionSelected ? 'border-gray-300 bg-white text-gray-700' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                                {{-- CO명은 자동 입력되므로 수정 불가 처리 --}}
                            </div>

                            {{-- 지원날짜 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    지원 날짜 <span class="text-red-500">*</span>
                                </label>
                                <input type="date"
                                       wire:model="formSupportDate"
                                       @disabled(!$institutionSelected)
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                              {{ $errors->has('formSupportDate') ? 'border-red-400' : '' }}
                                              {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                                @error('formSupportDate')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- 지원방법 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">지원 방법</label>
                                <select wire:model="formSupportType"
                                        @disabled(!$institutionSelected)
                                        class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                               {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}">
                                    <option>전화</option>
                                    <option>대면</option>
                                    <option>화상</option>
                                    <option>이메일</option>
                                    <option>문자</option>
                                    <option>기타</option>
                                </select>
                            </div>

                            {{-- 지원시간 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    지원 시간 <span class="text-red-500">*</span>
                                </label>
                                <input type="time"
                                       wire:model="formSupportTime"
                                       @disabled(!$institutionSelected)
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                              {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                            </div>

                            {{-- 참석자 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">참석자</label>
                                <input type="text"
                                       wire:model="formTarget"
                                       @disabled(!$institutionSelected)
                                       placeholder="예: 원장, 교사 2명"
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                              {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                            </div>

                        </div>

                        {{-- 구분선 --}}
                        <div class="border-t border-gray-100 pt-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">기관 이슈 및 논의 사항</h3>

                            {{-- 기관과의 소통내용 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">기관과의 소통내용</label>
                                <textarea wire:model="formToAccount"
                                          @disabled(!$institutionSelected)
                                          rows="5"
                                          placeholder="기관과 나눈 주요 대화 내용을 기록해 주세요 (Enter 시 새 줄에 ▶ 추가)"
                                          x-on:keydown.enter="mochiSupportEnterTriangle($event)"
                                          class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none
                                                 {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}">
                                </textarea>
                            </div>
                        </div>

                    </div>

                    {{-- 모달 하단 버튼 영역 --}}
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between flex-shrink-0 rounded-b-2xl">

                        {{-- 완료처리 토글 --}}
                        <label class="flex items-center gap-3 cursor-pointer">
                            <span class="text-sm font-medium text-gray-700">완료처리</span>
                            <button type="button"
                                    wire:click="$toggle('formCompleted')"
                                    @disabled(!$institutionSelected)
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent
                                           transition-colors duration-200 focus:outline-none
                                           {{ $formCompleted ? 'bg-green-500' : 'bg-gray-300' }}
                                           {{ $institutionSelected ? '' : 'opacity-50 cursor-not-allowed' }}">
                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200
                                             {{ $formCompleted ? 'translate-x-5' : 'translate-x-0' }}">
                                </span>
                            </button>
                            <span class="text-xs {{ $formCompleted ? 'text-green-600 font-medium' : 'text-gray-400' }}">
                                {{ $formCompleted ? '완료됨' : '진행중' }}
                            </span>
                        </label>

                        {{-- 버튼들 --}}
                        <div class="flex items-center gap-3">
                            <button type="button"
                                    wire:click="closeModal"
                                    class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                                취소하기
                            </button>
                            <button type="submit"
                                    @disabled(!$institutionSelected)
                                    class="px-5 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-70 cursor-not-allowed">
                                <span wire:loading.remove wire:target="save">저장하기</span>
                                <span wire:loading wire:target="save">저장 중...</span>
                            </button>
                        </div>

                    </div>
                </form>

            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         CO 파일업로드 / 계약서 업로드 모달
    ═══════════════════════════════════════════════════════════════ --}}
    @if($showContractModal)
        <div class="mochi-modal-overlay z-[55]" wire:click.self="closeContractUploadModal">
            <div class="mochi-modal-shell max-w-6xl h-[90vh] max-h-[90vh] flex flex-col" wire:click.stop>
                {{-- CO 파일업로드 + 계약서 업로드 헤더 통합 --}}
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 bg-white flex-shrink-0 rounded-t-xl">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-900">계약서 파일 Upload</h2>
                        <p class="text-xs text-gray-500 mt-1">CO 파일 업로드 · 계약서 PDF·이미지·문서를 등록합니다.</p>
                    </div>
                    <button type="button" wire:click="closeContractUploadModal"
                            class="shrink-0 p-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 cursor-pointer shadow-sm"
                            aria-label="닫기">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit="uploadContractDocument" class="flex flex-col flex-1 min-h-0">
                    <div class="px-6 py-4 space-y-5 overflow-y-auto flex-1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">기관명 <span class="text-red-500">*</span></label>
                                <select wire:model.live="contractSkCode"
                                        class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">기관을 선택하세요</option>
                                    @foreach($institutions as $inst)
                                        <option value="{{ $inst->SKcode }}">[{{ $inst->SKcode }}] {{ $inst->AccountName }}</option>
                                    @endforeach
                                </select>
                                @error('contractSkCode') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">변경된 기관명</label>
                                <input type="text" wire:model="contractChangedAccountName"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="변경된 기관명이 있으면 입력" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">사업자 번호</label>
                            <input type="text" wire:model="contractBusinessNumber"
                                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 max-w-md"
                                   placeholder="사업자등록번호" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">날짜 <span class="text-red-500">*</span></label>
                                <input type="date" wire:model="contractDocumentDate"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('contractDocumentDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">시간 <span class="text-red-500">*</span></label>
                                <input type="time" wire:model="contractDocumentTime"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('contractDocumentTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">담당자</label>
                            <input type="text" wire:model="contractConsultant"
                                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 max-w-md"
                                   placeholder="담당자명" />
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-semibold text-gray-800 mb-3">파일 Upload</h4>
                            <div class="flex flex-col lg:flex-row gap-4">
                                <div class="flex-1 min-w-0 border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="overflow-x-auto max-h-64 overflow-y-auto">
                                        <table class="w-full text-xs">
                                            <thead class="bg-gray-50 border-b border-gray-200 sticky top-0">
                                                <tr class="text-gray-600">
                                                    <th class="px-2 py-2 text-left font-medium">
                                                        <span class="inline-flex items-center gap-1">ID <svg class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a1 1 0 012 0v6.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L5 10.586V4z"/></svg></span>
                                                    </th>
                                                    <th class="px-2 py-2 text-left font-medium">날짜</th>
                                                    <th class="px-2 py-2 text-left font-medium">Consultant</th>
                                                    <th class="px-2 py-2 text-left font-medium">SKcode</th>
                                                    <th class="px-2 py-2 text-left font-medium">기관명</th>
                                                    <th class="px-2 py-2 text-left font-medium">사업자번호</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @forelse($contractDocumentRows as $doc)
                                                    <tr wire:key="contract-doc-{{ $doc->id }}"
                                                        wire:click="selectContractDocument({{ $doc->id }})"
                                                        class="cursor-pointer hover:bg-blue-50/80 {{ $contractSelectedId === $doc->id ? 'bg-blue-50' : '' }}">
                                                        <td class="px-2 py-2 text-gray-700">{{ $doc->id }}</td>
                                                        <td class="px-2 py-2 text-gray-700 whitespace-nowrap">
                                                            {{ $doc->document_date?->format('Y-m-d') ?? '-' }}
                                                            @if($doc->document_time)
                                                                <span class="text-gray-500">{{ substr((string) $doc->document_time, 0, 5) }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-2 py-2 text-gray-700 max-w-[100px] truncate" title="{{ $doc->consultant }}">{{ $doc->consultant ?? '-' }}</td>
                                                        <td class="px-2 py-2 text-gray-700 font-mono text-[11px]">{{ $doc->sk_code }}</td>
                                                        <td class="px-2 py-2 text-gray-700 max-w-[140px] truncate" title="{{ $doc->account_name }}">{{ $doc->account_name }}</td>
                                                        <td class="px-2 py-2 text-gray-600">{{ $doc->business_number ?? '-' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                                            @if(filled($contractSkCode))
                                                                등록된 계약서 파일이 없습니다. 우측에서 파일을 선택한 뒤 하단「업로드」를 누르세요.
                                                            @else
                                                                기관을 선택하면 해당 기관의 계약서 목록이 표시됩니다.
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="w-full lg:w-72 flex flex-col gap-3 shrink-0">
                                    <input type="file" wire:model="contractUpload" id="contract-upload-input"
                                           class="hidden"
                                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/*" />
                                    <label for="contract-upload-input"
                                           class="flex items-center justify-center w-full px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg cursor-pointer transition-colors">
                                        파일 선택
                                    </label>
                                    <div wire:loading wire:target="contractUpload" class="text-xs text-blue-600">파일 처리 중…</div>
                                    @error('contractUpload') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                                    <div class="flex flex-col gap-2">
                                        @if($contractSelectedId)
                                            <a href="{{ route('contract-documents.preview', ['contractDocument' => $contractSelectedId]) }}"
                                               target="_blank" rel="noopener"
                                               class="px-3 py-2 text-sm text-center border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700">
                                                미리보기
                                            </a>
                                            <a href="{{ route('contract-documents.download', ['contractDocument' => $contractSelectedId]) }}"
                                               class="px-3 py-2 text-sm text-center border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700">
                                                다운로드
                                            </a>
                                        @else
                                            <span class="px-3 py-2 text-sm text-center border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">미리보기</span>
                                            <span class="px-3 py-2 text-sm text-center border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">다운로드</span>
                                        @endif
                                        <button type="button"
                                                wire:click="deleteSelectedContractDocument"
                                                @disabled(!$contractSelectedId)
                                                class="px-3 py-2 text-sm text-center border border-rose-200 text-rose-700 rounded-lg hover:bg-rose-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                                            삭제
                                        </button>
                                    </div>

                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center text-xs text-gray-500 min-h-[100px] flex items-center justify-center">
                                        @if($contractUpload)
                                            <span class="text-gray-800 break-all">{{ $contractUpload->getClientOriginalName() }}</span>
                                        @else
                                            파일을 선택하면 이름이 여기에 표시됩니다.
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-2 flex-shrink-0">
                        <button type="button" wire:click="closeContractUploadModal"
                                class="px-5 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 cursor-pointer">
                            취소하기
                        </button>
                        <button type="submit"
                                class="px-6 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg cursor-pointer"
                                wire:loading.attr="disabled"
                                wire:target="uploadContractDocument,contractUpload">
                            <span wire:loading.remove wire:target="uploadContractDocument">업로드</span>
                            <span wire:loading wire:target="uploadContractDocument">업로드 중…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- 로딩 인디케이터: target 없이 두면 wire:model.live(필터·검색)마다 매번 뜸 --}}
    <div wire:loading.delay
         wire:target="save,uploadContractDocument,toggleComplete,deleteSelectedContractDocument,gotoPage,nextPage,previousPage"
         class="fixed bottom-6 right-6 z-50">
        <div class="bg-white rounded-xl px-4 py-3 shadow-lg border border-gray-200 flex items-center gap-2 text-sm text-gray-700">
            <svg class="animate-spin w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            처리 중...
        </div>
    </div>

</div>
