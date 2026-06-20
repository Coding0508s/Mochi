<div class="mochi-page">

    {{-- ───── 성공 메시지 ───── --}}
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

            {{-- 년도 선택 --}}
            <select wire:model.live="filterYear"
                    class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header max-lg:flex-1">
                <option value="">전체 년도</option>
                @foreach($years as $year)
                    <option value="{{ $year }}">{{ $year }}년</option>
                @endforeach
            </select>

            {{-- 담당자 필터 --}}
            <select wire:model.live="filterTr"
                    class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header max-lg:flex-1">
                <option value="">전체 담당</option>
                @foreach($trList as $tr)
                    <option value="{{ $tr }}">{{ $tr }}</option>
                @endforeach
            </select>

            {{-- 키워드 검색 (기관명·SK코드·이슈·소통내용) --}}
            <div class="relative min-w-0 flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="기관명, SK코드, 이슈, 소통내용 검색..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"/>
            </div>

            {{-- 건수 --}}
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

                @unless($crossTeamReadOnly ?? false)
                <a href="{{ \App\Support\TeamMenuContext::route('supports.create') }}"
                   class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border border-blue-300 bg-white px-3 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    기관지원 보고서 작성
                </a>

                <button type="button"
                        wire:click="openContractUploadModal"
                        class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    CO 파일업로드
                </button>
                @endunless
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
                    @can('deleteSupportRecords')
                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase">삭제</th>
                    @endcan
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                @forelse($records as $index => $record)
                    <tr wire:key="support-row-{{ $record->ID }}"
                        wire:click="openDetailModal({{ $record->ID }})"
                        class="cursor-pointer mochi-table-row-hover transition-colors
                               {{ ($record->is_urgent ?? false) ? 'bg-red-50/40' : '' }}
                               {{ $record->isCompleted() ? 'opacity-70' : '' }}">

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

                        {{-- 긴급 --}}
                        <td class="px-3 py-2.5">
                            @if($record->is_urgent ?? false)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">
                                    긴급
                                </span>
                            @else
                                <span class="text-xs text-gray-300">-</span>
                            @endif
                        </td>

                        {{-- 기관명 --}}
                        <td class="px-3 py-2.5 font-medium text-gray-900 max-w-40 truncate {{ ($record->is_urgent ?? false) ? 'text-red-700' : '' }}" title="{{ $record->Account_Name }}">
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
                            @if($record->Support_Type)
                                <span class="text-xs text-gray-600">{{ $record->Support_Type }}</span>
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
                            @if($record->isCompleted())
                                <span class="text-xs text-green-700">완료</span>
                            @else
                                <span class="text-xs text-gray-600">진행중</span>
                            @endif
                        </td>

                        {{-- 완료처리 토글: wire:click.stop으로 행 클릭 이벤트 차단 --}}
                        <td class="px-3 py-2.5 text-center">
                            @if(! ($crossTeamReadOnly ?? false))
                            @can('updateSupportRecord', $record)
                                <button wire:click.stop="toggleComplete({{ $record->ID }})"
                                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent
                                               transition-colors duration-200 focus:outline-none
                                               {{ $record->isCompleted() ? 'bg-green-500' : 'bg-gray-300' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200
                                                 {{ $record->isCompleted() ? 'translate-x-4' : 'translate-x-0' }}">
                                    </span>
                                </button>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endcan
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- 상세 보기 --}}
                        <td class="px-3 py-2.5 text-center">
                            <button wire:click.stop="openDetailModal({{ $record->ID }})"
                                    class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                    title="상세 보기">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                        </td>

                        @can('deleteSupportRecords')
                            <td class="px-3 py-2.5 text-center" onclick="event.stopPropagation()">
                                <button type="button"
                                        wire:click.stop="deleteRecord({{ $record->ID }})"
                                        wire:confirm="이 지원 보고서를 삭제할까요? 되돌릴 수 없습니다."
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="삭제">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </td>
                        @endcan

                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ \Illuminate\Support\Facades\Gate::allows('deleteSupportRecords') ? 15 : 14 }}" class="px-4 py-16 text-center text-gray-400">
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
                <x-admin.modal-header
                    :title="$modalViewOnly ? '기관 지원 내역' : '기관 지원 내역 수정'"
                    :subtitle="$modalViewOnly ? '기관 지원 보고서 · 조회' : '기관 지원 보고서'"
                    close-action="closeModal"
                />

                {{-- 모달 폼 (스크롤 가능) --}}
                <form @unless($modalViewOnly) wire:submit="save" @endunless class="flex-1 overflow-y-auto">
                    @php
                        // 기관 선택 전에는 나머지 입력을 막아 실수를 줄입니다.
                        $institutionSelected = filled($formSkCode);
                        $fieldsDisabled = $modalViewOnly || ! $institutionSelected;
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
                                       @disabled($fieldsDisabled)
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header
                                              {{ $errors->has('formSkCode') ? 'border-red-400' : 'border-gray-300' }}
                                              {{ $fieldsDisabled ? 'bg-gray-50 text-gray-700 cursor-not-allowed' : '' }}" />

                                @if(! $modalViewOnly && filled($formInstitutionKeyword) && blank($formSkCode) && $institutionSuggestions->isNotEmpty())
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">담당자</label>
                                <input type="text"
                                       wire:model="formCoName"
                                       @disabled($fieldsDisabled)
                                       class="w-full py-2 px-3 text-sm border rounded-lg
                                              {{ $fieldsDisabled ? 'border-gray-200 bg-gray-50 text-gray-700 cursor-not-allowed' : 'border-gray-300 bg-white text-gray-700' }}"/>
                                {{-- CO명은 자동 입력되므로 수정 불가 처리 --}}
                            </div>

                            {{-- 지원날짜 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    지원 날짜 <span class="text-red-500">*</span>
                                </label>
                                <input type="date"
                                       wire:model="formSupportDate"
                                       @disabled($fieldsDisabled)
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header
                                              {{ $errors->has('formSupportDate') ? 'border-red-400' : '' }}
                                              {{ $fieldsDisabled ? 'border-gray-200 bg-gray-50 text-gray-700 cursor-not-allowed' : 'border-gray-300' }}"/>
                                @error('formSupportDate')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- 지원방법 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">지원 방법</label>
                                <select wire:model="formSupportType"
                                        @disabled($fieldsDisabled)
                                        class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header
                                               {{ $fieldsDisabled ? 'border-gray-200 bg-gray-50 text-gray-700 cursor-not-allowed' : 'border-gray-300' }}">
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
                                       @disabled($fieldsDisabled)
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header
                                              {{ $fieldsDisabled ? 'border-gray-200 bg-gray-50 text-gray-700 cursor-not-allowed' : 'border-gray-300' }}"/>
                            </div>

                            {{-- 참석자 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">참석자</label>
                                <input type="text"
                                       wire:model="formTarget"
                                       @disabled($fieldsDisabled)
                                       placeholder="예: 원장, 교사 2명"
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header
                                              {{ $fieldsDisabled ? 'border-gray-200 bg-gray-50 text-gray-700 cursor-not-allowed' : 'border-gray-300' }}"/>
                            </div>

                        </div>

                        {{-- 구분선 --}}
                        <div class="border-t border-gray-100 pt-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">기관 이슈 및 논의 사항</h3>

                            {{-- 기관과의 소통내용 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">기관과의 소통내용</label>
                                <textarea wire:model="formToAccount"
                                          @disabled($fieldsDisabled)
                                          rows="5"
                                          placeholder="기관과 나눈 주요 대화 내용을 기록해 주세요 (Enter 시 새 줄에 ▶ 추가)"
                                          @unless($modalViewOnly) x-on:keydown.enter="mochiSupportEnterTriangle($event)" @endunless
                                          class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header resize-none
                                                 {{ $fieldsDisabled ? 'border-gray-200 bg-gray-50 text-gray-700 cursor-not-allowed' : 'border-gray-300' }}">
                                </textarea>
                            </div>
                        </div>

                    </div>

                    {{-- 모달 하단 버튼 영역 --}}
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 flex-shrink-0 rounded-b-2xl">

                        @unless($modalViewOnly)
                            <div class="flex flex-wrap items-center gap-3">
                                @can('deleteSupportRecords')
                                    @if($editingId)
                                        <button type="button"
                                                wire:click="deleteRecord({{ $editingId }})"
                                                wire:confirm="이 지원 보고서를 삭제할까요? 되돌릴 수 없습니다."
                                                class="px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                                            삭제
                                        </button>
                                    @endif
                                @endcan

                                {{-- 완료처리 토글 --}}
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <span class="text-sm font-medium text-gray-700">완료처리</span>
                                    <button type="button"
                                            wire:click="$toggle('formCompleted')"
                                            @disabled(! $institutionSelected)
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
                            </div>
                        @else
                            <div class="text-sm text-gray-500">
                                @if($formCompleted)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">완료</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">진행중</span>
                                @endif
                            </div>
                        @endunless

                        {{-- 버튼들 --}}
                        <div class="flex items-center gap-3">
                            @if($modalViewOnly)
                                <button type="button"
                                        wire:click="closeModal"
                                        class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                                    닫기
                                </button>
                                @if($this->canUpdateEditingRecord())
                                    <button type="button"
                                            wire:click="startModalEdit"
                                            class="px-5 py-2 text-sm font-medium bg-mochi-header hover:bg-mochi-header/90 text-white rounded-lg transition-colors">
                                        수정
                                    </button>
                                @endif
                            @else
                                <button type="button"
                                        wire:click="cancelModalEdit"
                                        class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                                    취소
                                </button>
                                <button type="submit"
                                        @disabled(! $institutionSelected)
                                        class="px-5 py-2 text-sm font-medium bg-mochi-header hover:bg-mochi-header/90 text-white rounded-lg transition-colors"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-70 cursor-not-allowed">
                                    <span wire:loading.remove wire:target="save">저장하기</span>
                                    <span wire:loading wire:target="save">저장 중...</span>
                                </button>
                            @endif
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
                <x-admin.modal-header
                    title="계약서 파일 Upload"
                    subtitle="CO 파일 업로드 · 계약서 PDF·이미지·문서를 등록합니다."
                    close-action="closeContractUploadModal"
                    class="rounded-t-xl bg-white"
                />

                <form wire:submit="saveContractDocument" class="flex flex-col flex-1 min-h-0">
                    <div class="px-6 py-4 space-y-5 overflow-y-auto flex-1">
                        @if($contractSelectedId)
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                선택한 파일(ID: {{ $contractSelectedId }}) 수정 모드입니다. 파일을 새로 선택하면 실제 파일이 교체됩니다.
                            </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">기관명 <span class="text-red-500">*</span></label>
                                <select wire:model.live="contractSkCode"
                                        class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                    <option value="">기관을 선택하세요</option>
                                    @foreach($institutions as $inst)
                                        <option value="{{ $inst->SKcode }}">[{{ $inst->SKcode }}] {{ $inst->resolvedAccountName() }}</option>
                                    @endforeach
                                </select>
                                @error('contractSkCode') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">변경된 기관명</label>
                                <input type="text" wire:model="contractChangedAccountName"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"
                                       placeholder="변경된 기관명이 있으면 입력" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">사업자 번호</label>
                            <input type="text" wire:model="contractBusinessNumber"
                                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header max-w-md"
                                   placeholder="사업자등록번호" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">날짜 <span class="text-red-500">*</span></label>
                                <input type="date" wire:model="contractDocumentDate"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                @error('contractDocumentDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">시간 <span class="text-red-500">*</span></label>
                                <input type="time" wire:model="contractDocumentTime"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                @error('contractDocumentTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">담당자</label>
                            <input type="text" wire:model="contractConsultant"
                                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header max-w-md"
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
                                                    <th class="px-2 py-2 text-left font-medium">파일명</th>
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
                                                        <td class="px-2 py-2 text-gray-700 max-w-[200px] truncate" title="{{ $doc->original_filename }}">{{ $doc->original_filename ?? '-' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                            </svg>
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
                                           class="flex items-center justify-center w-full px-4 py-2.5 text-sm font-medium text-white bg-mochi-header hover:bg-mochi-header/90 rounded-lg cursor-pointer transition-colors">
                                        파일 선택
                                    </label>
                                    <div wire:loading wire:target="contractUpload" class="text-xs text-blue-600">파일 처리 중…</div>
                                    @error('contractUpload') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                                    <div class="flex flex-col gap-2">
                                        @if($contractSelectedId)
                                            <button type="button"
                                                    wire:click="clearSelectedContractDocument"
                                                    class="px-3 py-2 text-sm text-center border border-blue-200 text-blue-700 rounded-lg hover:bg-blue-50">
                                                신규 등록 모드
                                            </button>
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
                                        @elseif($contractSelectedId)
                                            <span class="text-gray-700 break-all">현재 파일 유지 (교체하려면 새 파일을 선택하세요)</span>
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
                                class="px-6 py-2 text-sm font-medium bg-mochi-header hover:bg-mochi-header/90 text-white rounded-lg cursor-pointer"
                                wire:loading.attr="disabled"
                                wire:target="saveContractDocument,contractUpload">
                            <span wire:loading.remove wire:target="saveContractDocument">{{ $contractSelectedId ? '수정 저장' : '업로드' }}</span>
                            <span wire:loading wire:target="saveContractDocument">{{ $contractSelectedId ? '수정 중…' : '업로드 중…' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- 로딩 인디케이터: target 없이 두면 wire:model.live(필터·검색)마다 매번 뜸 --}}
    <div wire:loading.delay
         wire:target="save,saveContractDocument,toggleComplete,deleteSelectedContractDocument,gotoPage,nextPage,previousPage"
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
