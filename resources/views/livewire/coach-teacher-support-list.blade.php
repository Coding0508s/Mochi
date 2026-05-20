<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm" data-mochi-flash-dismiss="3000" role="status">
            {{ session('success') }}
        </div>
    @endif

    {{-- Summary --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">교사 지원 현황</h2>
            <span class="text-gray-300">|</span>
            <button wire:click="setKpiFilter('first_round')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $kpiFilter === 'first_round' ? 'font-semibold text-blue-700' : '' }}">
                1차 지원 <span class="font-semibold text-blue-600">{{ $kpis['first_round'] }}</span>
            </button>
            <button wire:click="setKpiFilter('second_round')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $kpiFilter === 'second_round' ? 'font-semibold text-blue-700' : '' }}">
                2차 지원 <span class="font-semibold text-blue-600">{{ $kpis['second_round'] }}</span>
            </button>
            <button wire:click="setKpiFilter('completed')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $kpiFilter === 'completed' ? 'font-semibold text-green-700' : '' }}">
                지원 완료 <span class="font-semibold text-green-600">{{ $kpis['completed'] }}</span>
            </button>
            <button wire:click="setKpiFilter('unsupported')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $kpiFilter === 'unsupported' ? 'font-semibold text-red-700' : '' }}">
                미지원 <span class="font-semibold text-red-500">{{ $kpis['unsupported'] }}</span>
            </button>
            <div class="ml-auto flex items-center gap-3">
                <span class="text-gray-500">{{ $filterYear }}년</span>
                <a href="{{ route('supports.index') }}"
                   class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600">
                    기관지원보고서
                </a>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="filterYear"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                @for($y = now()->year; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}">{{ $y }}년</option>
                @endfor
            </select>

            <div class="flex items-center gap-1 text-sm">
                <button wire:click="$set('filterRound', '')"
                        class="px-3 py-1.5 rounded-lg {{ $filterRound === '' ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                    전체
                </button>
                <button wire:click="$set('filterRound', '1')"
                        class="px-3 py-1.5 rounded-lg {{ $filterRound === '1' ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                    1차
                </button>
                <button wire:click="$set('filterRound', '2')"
                        class="px-3 py-1.5 rounded-lg {{ $filterRound === '2' ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                    2차
                </button>
            </div>

            <select wire:model.live="filterMonth"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">계획 월 전체</option>
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}">{{ $m }}월</option>
                @endfor
            </select>

            @if($search || $filterRound || $filterMonth || $kpiFilter)
                <button wire:click="resetFilters"
                        class="py-2 px-3 text-sm text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                    초기화
                </button>
            @endif

            <div class="relative flex-1 min-w-56">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="이름, 기관명, SK코드 검색"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600 ml-auto">
                <input type="checkbox" wire:model.live="showAllTeachers" {{ $showAllTeachers ? 'checked' : '' }}
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                퇴직 포함
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" wire:click="$toggle('showExtendedColumns')" {{ $showExtendedColumns ? 'checked' : '' }}
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                확장 컬럼
            </label>
        </div>
    </div>

    {{-- Table --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap border-collapse">
                <thead class="mochi-table-head">
                <tr class="text-gray-700">
                    <th class="px-3 py-2 text-center sticky left-0 bg-[#e8e6d9] z-10 border border-gray-300">SK_Code</th>
                    <th class="px-3 py-2 text-center sticky left-[90px] bg-[#e8e6d9] z-10 border border-gray-300">기관명</th>
                    <th class="px-3 py-2 text-left border border-gray-300">직급</th>
                    <th class="px-3 py-2 text-left border border-gray-300">이름</th>
                    <th class="px-3 py-2 text-left border border-gray-300">1차 지원<br>계획일자</th>
                    <th class="px-3 py-2 text-left border border-gray-300">1차 지원<br>계획타입</th>
                    <th class="px-3 py-2 text-left border border-gray-300">2차 지원<br>계획일자</th>
                    <th class="px-3 py-2 text-left border border-gray-300">2차 지원<br>계획타입</th>
                    <th class="px-3 py-2 text-center border border-gray-300 w-[50px]"></th>
                    <th class="px-3 py-2 text-left border border-gray-300">1차 지원<br>완료일</th>
                    <th class="px-3 py-2 text-left border border-gray-300">1차 완료<br>타입</th>
                    <th class="px-3 py-2 text-left border border-gray-300">2차 지원<br>완료일</th>
                    <th class="px-3 py-2 text-left border border-gray-300">2차 완료<br>타입</th>
                    @if($showExtendedColumns)
                        <th class="px-3 py-2 text-left border border-gray-300">3차 완료</th>
                        <th class="px-3 py-2 text-left border border-gray-300">4차 완료</th>
                        <th class="px-3 py-2 text-left border border-gray-300">GS Essentials</th>
                        <th class="px-3 py-2 text-left border border-gray-300">LS Essentials</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @php
                    // rowspan 계산: 현재 페이지 내에서 같은 SK_Code별 연속 행 수
                    $items = $teachers->items();
                    $rowspans = [];
                    $i = 0;
                    while ($i < count($items)) {
                        $sk = $items[$i]->SK_Code;
                        $count = 1;
                        while ($i + $count < count($items) && $items[$i + $count]->SK_Code === $sk) {
                            $count++;
                        }
                        $rowspans[$i] = $count;
                        for ($j = 1; $j < $count; $j++) {
                            $rowspans[$i + $j] = 0;
                        }
                        $i += $count;
                    }
                @endphp

                @forelse($items as $idx => $teacher)
                    @php
                        $cols = config('coach_teacher_support.columns');
                        $isFirstInGroup = ($rowspans[$idx] ?? 0) > 0;
                        $span = $rowspans[$idx] ?? 0;
                    @endphp
                    <tr wire:key="teacher-{{ $teacher->ID }}"
                        wire:click="openEditModal({{ $teacher->ID }})"
                        class="mochi-table-row-hover cursor-pointer border border-gray-200 {{ $idx % 2 === 0 ? '' : '' }}">
                        @if($isFirstInGroup)
                            <td class="px-3 py-2 sticky left-0 bg-white z-10 border border-gray-200 align-middle text-center font-mono text-xs text-purple-700"
                                rowspan="{{ $span }}">
                                {{ ltrim((string) $teacher->SK_Code, '*') }}
                            </td>
                            <td class="px-3 py-2 sticky left-[90px] bg-white z-10 border border-gray-200 align-middle text-center"
                                rowspan="{{ $span }}">
                                <button type="button"
                                        class="text-blue-700 underline text-center hover:text-blue-900 cursor-pointer"
                                        wire:click.stop="openInstitutionModal('{{ $teacher->SK_Code }}')">
                                    {{ $teacher->institution?->AccountName ?? $teacher->School_Name }}
                                </button>
                            </td>
                        @endif
                        <td class="px-3 py-2 border border-gray-200">
                            @if($teacher->Position)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                    {{ $teacher->Position === '원장' ? 'bg-yellow-100 text-yellow-800' :
                                       ($teacher->Position === '교수부장' ? 'bg-purple-100 text-purple-700' :
                                       ($teacher->Position === '부원장' ? 'bg-pink-100 text-pink-700' :
                                       'bg-green-100 text-green-700')) }}">
                                    {{ $teacher->Position }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 border border-gray-200">
                            <button type="button"
                                    class="text-blue-700 underline text-left hover:text-blue-900 cursor-pointer"
                                    wire:click.stop="openTeacherModal({{ $teacher->ID }})">
                                {{ $teacher->Name }}
                            </button>
                        </td>
                        <td class="px-3 py-2 border border-gray-200">{{ \App\Support\ExcelSerialDate::formatPlanMonth($teacher->{$cols['plan_1st']}) }}</td>
                        <td class="px-3 py-2 border border-gray-200">{{ $teacher->{$cols['plan_type_1st']} }}</td>
                        <td class="px-3 py-2 border border-gray-200">{{ \App\Support\ExcelSerialDate::formatPlanMonth($teacher->{$cols['plan_2nd']}) }}</td>
                        <td class="px-3 py-2 border border-gray-200">{{ $teacher->{$cols['plan_type_2nd']} }}</td>
                        <td class="px-3 py-2 border border-gray-200 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700 cursor-pointer hover:bg-gray-300">Edit</span>
                        </td>
                        <td class="px-3 py-2 border border-gray-200 {{ $teacher->{$cols['completed_1st']} ? 'bg-green-50' : '' }}">
                            {{ $teacher->{$cols['completed_1st']}?->format('Y-m-d') }}
                        </td>
                        <td class="px-3 py-2 border border-gray-200">{{ $teacher->{$cols['type_1st']} }}</td>
                        <td class="px-3 py-2 border border-gray-200 {{ $teacher->{$cols['completed_2nd']} ? 'bg-green-50' : '' }}">
                            {{ $teacher->{$cols['completed_2nd']}?->format('Y-m-d') }}
                        </td>
                        <td class="px-3 py-2 border border-gray-200">{{ $teacher->{$cols['type_2nd']} }}</td>
                        @if($showExtendedColumns)
                            <td class="px-3 py-2 border border-gray-200">{{ $teacher->{$cols['completed_3rd']}?->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 border border-gray-200">{{ $teacher->{$cols['completed_4th']}?->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 border border-gray-200">{{ $teacher->{$cols['essentials_gs']}?->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 border border-gray-200">{{ $teacher->{$cols['essentials_ls']}?->format('Y-m-d') }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showExtendedColumns ? 17 : 13 }}" class="px-4 py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p>조건에 맞는 교사가 없습니다.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($teachers->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $teachers->links() }}
            </div>
        @endif
    </div>

    {{-- Institution Info Modal --}}
    @if($showInstitutionModal && $institutionInfo)
        <div class="mochi-modal-overlay" wire:click.self="closeInstitutionModal">
            <div class="mochi-modal-shell max-w-4xl" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-blue-700">TR 기관정보조회</h3>
                    <button wire:click="closeInstitutionModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-4 max-h-[80vh] overflow-y-auto space-y-6">

                    {{-- 기관정보 --}}
                    <div>
                        <h4 class="text-base font-semibold text-blue-700 mb-3">기관정보</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">기관명:</span>
                                <span class="px-3 py-1.5 border border-gray-300 rounded flex-1">{{ $institutionInfo['name'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">Consultant:</span>
                                <span class="px-3 py-1.5 border border-gray-300 rounded flex-1">{{ $institutionInfo['co'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">주소:</span>
                                <span class="px-3 py-1.5 border border-gray-300 rounded flex-1">{{ $institutionInfo['address'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">CS:</span>
                                <span class="px-3 py-1.5 border border-gray-300 rounded flex-1">{{ $institutionInfo['cs'] }}</span>
                            </div>
                            <div></div>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500 w-20 shrink-0">Coach:</span>
                                <span class="px-3 py-1.5 border border-gray-300 rounded flex-1">{{ $institutionInfo['tr'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- 기관 지원 내역 --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">기관 지원 내역:(완료처리)</h4>
                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead class="bg-yellow-100">
                                <tr>
                                    <th class="px-2 py-1.5 text-left border-b">ID</th>
                                    <th class="px-2 py-1.5 text-left border-b">코치명</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원날짜</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 타입</th>
                                    <th class="px-2 py-1.5 text-left border-b">기관이슈</th>
                                    <th class="px-2 py-1.5 text-left border-b">상태</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($institutionSupportHistory as $record)
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="px-2 py-1.5">{{ $record['id'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['coach'] }}</td>
                                        <td class="px-2 py-1.5 text-blue-600 underline">{{ $record['date'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['type'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['issue'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['status'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-2 py-3 text-center text-gray-400">내역 없음</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- 교사 지원 내역 --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">교사 지원 내역:</h4>
                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead class="bg-yellow-100">
                                <tr>
                                    <th class="px-2 py-1.5 text-left border-b">ID</th>
                                    <th class="px-2 py-1.5 text-left border-b">코치명</th>
                                    <th class="px-2 py-1.5 text-left border-b">교사명</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 날짜</th>
                                    <th class="px-2 py-1.5 text-left border-b">상태</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 타입</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($teacherSupportHistory as $record)
                                    <tr class="border-b border-gray-100 hover:bg-blue-50 cursor-pointer"
                                        @if(!empty($record['detail_key']))
                                            wire:click="openTeacherSupportHistoryDetail('{{ $record['detail_key'] }}', {{ $record['teacher_id'] ?? 'null' }})"
                                        @endif>
                                        <td class="px-2 py-1.5">{{ $record['id'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['coach'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['teacher'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['date'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['status'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['type'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-2 py-3 text-center text-gray-400">내역 없음</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Contacts --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Contacts:</h4>
                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead class="bg-red-100">
                                <tr>
                                    <th class="px-2 py-1.5 text-left border-b">ID</th>
                                    <th class="px-2 py-1.5 text-left border-b">이름</th>
                                    <th class="px-2 py-1.5 text-left border-b">연락처</th>
                                    <th class="px-2 py-1.5 text-left border-b">이메일</th>
                                    <th class="px-2 py-1.5 text-left border-b">GrapeSEED</th>
                                    <th class="px-2 py-1.5 text-left border-b">LittleSEED</th>
                                    <th class="px-2 py-1.5 text-left border-b">마지막 지원날짜</th>
                                    <th class="px-2 py-1.5 text-left border-b">마지막 지원타입</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($institutionContacts as $contact)
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="px-2 py-1.5">{{ $contact['id'] }}</td>
                                        <td class="px-2 py-1.5 {{ $contact['position'] === '원장' ? 'bg-pink-100' : '' }}">
                                            {{ $contact['name'] }}
                                        </td>
                                        <td class="px-2 py-1.5">{{ $contact['phone'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['email'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['gs_essentials'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['ls_essentials'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['last_support_date'] }}</td>
                                        <td class="px-2 py-1.5">{{ $contact['last_support_type'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="px-2 py-3 text-center text-gray-400">연락처 없음</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endif

    {{-- Edit Modal --}}
    @if($showEditModal && $editingTeacherId)
        <div class="mochi-modal-overlay" wire:click.self="closeEditModal">
            <div class="mochi-modal-shell max-w-2xl" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">지원 일정 수정</h3>
                    <button wire:click="closeEditModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-4 space-y-6 max-h-[70vh] overflow-y-auto">
                    {{-- 1·2차 계획 --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">계획</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">1차 계획일</label>
                                <input type="date" wire:model="editForm.plan_1st"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">1차 계획 타입</label>
                                <select wire:model="editForm.plan_type_1st"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">-</option>
                                    @foreach($supportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">2차 계획일</label>
                                <input type="date" wire:model="editForm.plan_2nd"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">2차 계획 타입</label>
                                <select wire:model="editForm.plan_type_2nd"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">-</option>
                                    @foreach($supportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- 1·2차 완료 --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">완료</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">1차 완료일</label>
                                <input type="date" wire:model="editForm.completed_1st"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">1차 타입</label>
                                <select wire:model="editForm.type_1st"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">-</option>
                                    @foreach($supportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">2차 완료일</label>
                                <input type="date" wire:model="editForm.completed_2nd"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">2차 타입</label>
                                <select wire:model="editForm.type_2nd"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">-</option>
                                    @foreach($supportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- 3·4차 완료 --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">3·4차 완료</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">3차 완료일</label>
                                <input type="date" wire:model="editForm.completed_3rd"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">3차 타입</label>
                                <select wire:model="editForm.type_3rd"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">-</option>
                                    @foreach($supportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">4차 완료일</label>
                                <input type="date" wire:model="editForm.completed_4th"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">4차 타입</label>
                                <select wire:model="editForm.type_4th"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">-</option>
                                    @foreach($supportTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Essentials --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Essentials</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">GrapeSEED Essentials</label>
                                <input type="date" wire:model="editForm.essentials_gs"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">LittleSEED Essentials</label>
                                <input type="date" wire:model="editForm.essentials_ls"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t">
                    <button wire:click="closeEditModal"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        취소
                    </button>
                    <button wire:click="saveEditForm"
                            class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                        저장
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Teacher Detail Modal --}}
    @if($showTeacherModal && $teacherDetailInfo)
        <div class="mochi-modal-overlay" wire:click.self="closeTeacherModal">
            <div class="mochi-modal-shell max-w-4xl" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-blue-50/80 to-white">
                    <h3 class="text-lg font-semibold text-blue-700">TR 교사정보</h3>
                    <div class="flex items-center gap-2">
                        @if(!$teacherModalEditMode && $teacherDetailInfo['class_in_out'])
                            <button wire:click="confirmRetireTeacher"
                                    class="px-3 py-1.5 text-xs text-red-700 border border-red-300 rounded-lg hover:bg-red-50 cursor-pointer">
                                퇴직
                            </button>
                            <button wire:click="startTeacherEdit"
                                    class="px-3 py-1.5 text-xs text-amber-700 border border-amber-300 rounded-lg hover:bg-amber-50 cursor-pointer">
                                수정
                            </button>
                        @endif
                        <button wire:click="closeTeacherModal" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="px-6 py-4 max-h-[80vh] overflow-y-auto space-y-6">

                    {{-- 퇴직 확인 --}}
                    @if($confirmingRetire)
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-sm text-red-800 font-medium mb-3">정말 이 교사를 퇴직 처리하시겠습니까? 이 작업은 되돌릴 수 없습니다.</p>
                            <div class="flex gap-2">
                                <button wire:click="retireTeacher"
                                        class="px-3 py-1.5 text-xs text-white bg-red-600 rounded hover:bg-red-700 cursor-pointer">
                                    퇴직 확인
                                </button>
                                <button wire:click="cancelRetireTeacher"
                                        class="px-3 py-1.5 text-xs text-gray-600 border border-gray-300 rounded hover:bg-gray-50 cursor-pointer">
                                    취소
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- 수정 모드 --}}
                    @if($teacherModalEditMode)
                        <div>
                            <h4 class="text-base font-semibold text-gray-800 mb-3">교사 정보 수정</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">이름</label>
                                    <input type="text" wire:model="teacherProfileForm.name"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">이메일</label>
                                    <input type="email" wire:model="teacherProfileForm.email"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">전화</label>
                                    <input type="text" wire:model="teacherProfileForm.phone"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">직급</label>
                                    <input type="text" wire:model="teacherProfileForm.position"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">비고</label>
                                    <textarea wire:model="teacherProfileForm.description" rows="2"
                                              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-500">수업참여</label>
                                    <input type="checkbox" wire:model="teacherProfileForm.class_in_out"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-4">
                                <button wire:click="$set('teacherModalEditMode', false)"
                                        class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                                    취소
                                </button>
                                <button wire:click="saveTeacherProfile"
                                        class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 cursor-pointer">
                                    저장
                                </button>
                            </div>
                        </div>
                    @else
                    <div>
                        <h4 class="text-base font-semibold text-gray-800 mb-3">교사 정보</h4>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    <tr>
                                        <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">이름</th>
                                        <td class="px-3 py-2 font-medium text-gray-900">{{ $teacherDetailInfo['name'] ?? '-' }}</td>
                                        <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">GrapeSEED 이수</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['gs_essentials'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">이메일</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['email'] ?? '-' }}</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">LittleSEED 이수</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['ls_essentials'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">전화</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['phone'] ?? '-' }}</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 Coach</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['tr'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">직급</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['position'] ?? '-' }}</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">CS</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['cs'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">수업참여</th>
                                        <td class="px-3 py-2">
                                            @if($teacherDetailInfo['class_in_out'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">수업(O)</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">수업(X)</span>
                                            @endif
                                        </td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">CO</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['co'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관</th>
                                        <td class="px-3 py-2 text-gray-900">{{ $teacherDetailInfo['school_name'] ?? '-' }} ({{ $teacherDetailInfo['sk_code'] }})</td>
                                        <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium"></th>
                                        <td></td>
                                    </tr>
                                    @if($teacherDetailInfo['description'])
                                        <tr>
                                            <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">비고</th>
                                            <td colspan="3" class="px-3 py-2 text-gray-900 whitespace-pre-wrap">{{ $teacherDetailInfo['description'] }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    {{-- 지원 내역 --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">지원 내역</h4>
                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead class="bg-yellow-100">
                                <tr>
                                    <th class="px-2 py-1.5 text-left border-b">ID</th>
                                    <th class="px-2 py-1.5 text-left border-b">코치명</th>
                                    <th class="px-2 py-1.5 text-left border-b">교사명</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 날짜</th>
                                    <th class="px-2 py-1.5 text-left border-b">상태</th>
                                    <th class="px-2 py-1.5 text-left border-b">지원 타입</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($teacherDetailHistory as $record)
                                    <tr class="border-b border-gray-100 hover:bg-blue-50 cursor-pointer"
                                        @if(!empty($record['detail_key']))
                                            wire:click="openTeacherSupportHistoryDetail('{{ $record['detail_key'] }}', {{ $record['teacher_id'] ?? $teacherDetailInfo['id'] }})"
                                        @endif>
                                        <td class="px-2 py-1.5">{{ $record['id'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['coach'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['teacher'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['date'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['status'] }}</td>
                                        <td class="px-2 py-1.5">{{ $record['type'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-2 py-3 text-center text-gray-400">지원 내역 없음</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- 새 지원 Pill --}}
                    @if($teacherDetailInfo['class_in_out'] && !$teacherModalEditMode)
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">교사 지원 신규 작성:</h4>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach(config('coach_teacher_support_create.types', []) as $pill)
                                    @php
                                        $pillLabel = is_array($pill) ? ($pill['label'] ?? '') : (string) $pill;
                                        $pillAction = is_array($pill) ? ($pill['action'] ?? 'support_create') : 'support_create';
                                    @endphp
                                    @if($pillLabel === '')
                                        @continue
                                    @endif
                                    @if($pillAction === 'demo_lesson')
                                        <button type="button"
                                                wire:click.stop="openDemoLessonModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'lva_fr')
                                        <button type="button"
                                                wire:click.stop="openLvaFrModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'lva_fb')
                                        <button type="button"
                                                wire:click.stop="openLvaFbModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'ls_onsite_lva')
                                        <button type="button"
                                                wire:click.stop="openLsOnsiteLvaModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'littleseed_con')
                                        <button type="button"
                                                wire:click.stop="openLittleseedConModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'onsite')
                                        <button type="button"
                                                wire:click.stop="openOnsiteModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'pro_con')
                                        <button type="button"
                                                wire:click.stop="openProConModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'open_class')
                                        <button type="button"
                                                wire:click.stop="openOpenClassModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'unit21_plus')
                                        <button type="button"
                                                wire:click.stop="openUnit21PlusModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @elseif($pillAction === 'unit31_plus')
                                        <button type="button"
                                                wire:click.stop="openUnit31PlusModal({{ $teacherDetailInfo['id'] }})"
                                                class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-300 text-blue-800 bg-blue-100 hover:bg-blue-200 transition cursor-pointer">
                                            {{ $pillLabel }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>
                <div class="px-6 py-4 bg-gray-50 border-t flex justify-end">
                    <button wire:click="closeTeacherModal"
                            class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 cursor-pointer">
                        닫기
                    </button>
                </div>
            </div>
        </div>
    @endif

    @include('components.coach.demo-lesson-support-modal', ['demoLessonConfig' => $demoLessonConfig])
    @include('components.coach.lva-fr-support-modal', ['lvaFrConfig' => $lvaFrConfig])
    @include('components.coach.lva-fb-support-modal', ['lvaFbConfig' => $lvaFbConfig])
    @include('components.coach.ls-onsite-lva-support-modal', ['lsOnsiteLvaConfig' => $lsOnsiteLvaConfig])
    @include('components.coach.littleseed-con-support-modal', ['littleseedConConfig' => $littleseedConConfig])
    @include('components.coach.onsite-support-modal', ['onsiteConfig' => $onsiteConfig])
    @include('components.coach.pro-con-support-modal', ['proConConfig' => $proConConfig])
    @include('components.coach.open-class-support-modal', ['openClassConfig' => $openClassConfig])
    @include('components.coach.unit21-plus-support-modal', ['unit21PlusConfig' => $unit21PlusConfig])
    @include('components.coach.unit31-plus-support-modal', ['unit31PlusConfig' => $unit31PlusConfig])

    <x-coach.teacher-support-history-detail-modal
        :show="$showTeacherSupportHistoryDetailModal"
        :detail="$selectedTeacherSupportHistoryDetail"
    />
</div>
