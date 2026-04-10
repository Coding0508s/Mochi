<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- 상단 요약 영역 (잠재기관 페이지와 동일 톤) --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">기관리스트</h2>
            <span class="text-gray-300">|</span>
            <button wire:click="$set('assignmentFilter', '')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer">
                전체 <span class="font-semibold text-blue-600">{{ $allInstitutionCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'assigned')"
                    class="text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'assigned' ? 'font-semibold text-green-700' : '' }} cursor-pointer">
                담당자 배정 <span class="font-semibold text-green-600">{{ $assignedCoCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'unassigned')"
                    class="text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'unassigned' ? 'font-semibold text-red-700' : '' }} cursor-pointer">
                미배정 <span class="font-semibold text-red-500">{{ $unassignedCoCount }}</span>
            </button>
            <div class="ml-auto text-gray-500">
                현재 조건 결과: <span class="font-semibold text-gray-700">{{ $institutions->total() }}</span>건
            </div>
        </div>
    </div>

    {{-- 필터 영역 --}}
    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="filterGubun"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 구분</option>
                @foreach($gubunList as $gubun)
                    <option value="{{ $gubun }}">{{ $gubun }}</option>
                @endforeach
            </select>

            @if($search || $filterGubun)
                <button wire:click="$set('search', ''); $set('filterGubun', '')"
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
                       placeholder="기관명, SK코드, 원장명, 주소 검색"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            @if(config('features.institution_create_enabled'))
                <button type="button"
                        wire:click="openCreateModal"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                    신규 기관 등록
                </button>
            @else
                <button type="button"
                        disabled
                        title="현재 신규 기관 등록이 비활성화되어 있습니다. 활성화는 INSTITUTION_CREATE_ENABLED 설정을 참고하세요."
                        class="px-4 py-2 text-sm font-medium text-gray-500 bg-gray-200 rounded-lg cursor-not-allowed opacity-90">
                    신규 기관 등록
                </button>
            @endif
        </div>
    </div>

    {{-- 메인 리스트 테이블 --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                <tr class="text-gray-700">
                    <th class="px-3 py-2 text-left text-xs font-semibold">No</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">
                        <button wire:click="sort('SKcode')" class="flex items-center gap-1 hover:text-blue-700">
                            SK코드
                            @if($sortField === 'SKcode')
                                <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </button>
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">
                        <button wire:click="sort('AccountName')" class="flex items-center gap-1 hover:text-blue-700">
                            기관명
                            @if($sortField === 'AccountName')
                                <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </button>
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">담당 CO</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">담당 TR</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">구분</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold">현황</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">원장명</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">전화번호</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">주소</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($institutions as $index => $inst)
                    @php
                        $isTerminated = str_contains((string) ($inst->accountInfo?->Customer_Type ?? ''), '해지');
                    @endphp
                    <tr wire:key="institution-row-{{ $inst->ID }}"
                        wire:click="openDetailModal({{ $inst->ID }})"
                        class="mochi-table-row-hover transition-colors cursor-pointer">
                        <td class="px-3 py-2 text-gray-500 text-xs">{{ $institutions->firstItem() + $index }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                {{ $inst->SKcode ?? '-' }}
                            </span>
                        </td>
                        <td class="px-3 py-2 font-medium text-gray-900">
                            {{ $inst->AccountName ?? '-' }}
                            @if($inst->EnglishName)
                                <span class="block text-xs text-gray-400">{{ $inst->EnglishName }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $inst->accountInfo?->CO ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $inst->accountInfo?->TR ?? '-' }}</td>
                        <td class="px-3 py-2">
                            @if($inst->Gubun)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    {{ $inst->Gubun }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if($isTerminated)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                    해지
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    관리중
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $inst->Director ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $inst->Phone ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-500 max-w-56 truncate" title="{{ $inst->Address }}">
                            {{ $inst->Address ?? '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-16 text-center text-gray-400">
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

    {{-- 기관 상세 모달 --}}
    @if($showDetailModal && $selectedInstitution)
        <div class="mochi-modal-overlay"
             wire:click.self="closeDetailModal">
            <div class="mochi-modal-shell max-w-3xl"
                 wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50/80 to-white">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-block w-1.5 h-8 rounded-full bg-blue-600"></span>
                        <div>
                            <h2 class="text-xl font-extrabold tracking-tight text-gray-900">기관 상세 정보</h2>
                            <p class="text-sm text-gray-600 mt-0.5">
                                {{ $selectedInstitution['name'] ?? '-' }}
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                    {{ $selectedInstitution['skcode'] ?? '-' }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeDetailModal" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 grid grid-cols-2 gap-4 text-sm">
                    <div class="col-span-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs text-gray-500 mb-1">요약</div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                SK {{ $selectedInstitution['skcode'] ?? '-' }}
                            </span>
                            <span class="text-gray-600">담당 CO: <span class="font-medium text-gray-800">{{ $selectedInstitution['co'] ?? '-' }}</span></span>
                            <span class="text-gray-600">담당 TR: <span class="font-medium text-gray-800">{{ $selectedInstitution['tr'] ?? '-' }}</span></span>
                            <span class="text-gray-600">담당 CS: <span class="font-medium text-gray-800">{{ $selectedInstitution['cs'] ?? '-' }}</span></span>
                            <span class="text-gray-600">교사 수: <span class="font-medium text-gray-800">{{ $selectedInstitution['teacher_count'] ?? 0 }}</span></span>
                            <span class="text-gray-600">지원 내역: <span class="font-medium text-gray-800">{{ $selectedInstitution['support_count'] ?? 0 }}</span>건</span>
                        </div>
                    </div>

                    {{-- 기본정보를 테이블로 압축해 세로 공간을 줄입니다 --}}
                    <div class="col-span-2 border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">SKcode</th>
                                    <td class="px-3 py-2 font-mono text-sm font-semibold text-gray-900">{{ $selectedInstitution['skcode'] ?? '-' }}</td>
                                    <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관명</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['name'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">영문명</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['english_name'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">구분</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['gubun'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">고객유형</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($isEditingDetail)
                                            <div>
                                                <select wire:model.defer="editCustomerType"
                                                        class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    <option value="">선택</option>
                                                    @foreach($customerTypeOptions as $typeOption)
                                                        <option value="{{ $typeOption }}">{{ $typeOption }}</option>
                                                    @endforeach
                                                </select>
                                                @error('editCustomerType')
                                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @else
                                            {{ $selectedInstitution['customer_type'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">GS Number</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($isEditingDetail)
                                            <div>
                                                <input type="text"
                                                       wire:model.defer="editGsNo"
                                                       placeholder="GS Number 입력"
                                                       class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                                @error('editGsNo')
                                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @else
                                            {{ $selectedInstitution['gs_no'] ?? '-' }}
                                        @endif
                                    </td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 CO</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($isEditingDetail)
                                            <select wire:model.defer="editDetailCo"
                                                    class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">미지정</option>
                                                @foreach($coManagerOptions as $manager)
                                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                                @endforeach
                                            </select>
                                            @error('editDetailCo')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['co'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 TR</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($isEditingDetail)
                                            <select wire:model.defer="editDetailTr"
                                                    class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">미지정</option>
                                                @foreach($trManagerOptions as $manager)
                                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                                @endforeach
                                            </select>
                                            @error('editDetailTr')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['tr'] ?? '-' }}
                                        @endif
                                    </td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 CS</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($isEditingDetail)
                                            <select wire:model.defer="editDetailCs"
                                                    class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">미지정</option>
                                                @foreach($csManagerOptions as $manager)
                                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                                @endforeach
                                            </select>
                                            @error('editDetailCs')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['cs'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">원장명</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['director'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">대표전화</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['phone'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">직통 연락처</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['account_tel'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">최근 지원일</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        {{ $selectedInstitution['latest_support_date'] ? substr((string) $selectedInstitution['latest_support_date'], 0, 10) : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">주소</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['address'] ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- 최근 10년 지원/소통 이력 --}}
                    <div class="col-span-2 mt-2">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-base font-bold text-[#1f4f8f] flex items-center gap-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-[#2b78c5]"></span>
                                최근 10년 지원/소통 이력
                            </h3>
                            <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                                총 {{ count($supportHistory) }}건
                            </span>
                        </div>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="max-h-44 overflow-y-auto overflow-x-auto">
                                <table class="w-full text-xs whitespace-nowrap">
                                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                                    <tr class="text-gray-600">
                                        <th class="px-3 py-2 text-left">지원일</th>
                                        <th class="px-3 py-2 text-left">시간</th>
                                        <th class="px-3 py-2 text-left">담당자</th>
                                        <th class="px-3 py-2 text-left">지원방법</th>
                                        <th class="px-3 py-2 text-left">참석자</th>
                                        <th class="px-3 py-2 text-left">이슈/방문목적</th>
                                        <th class="px-3 py-2 text-left">소통내용</th>
                                        <th class="px-3 py-2 text-center">상태</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                    @forelse($supportHistory as $history)
                                        <tr wire:key="support-history-{{ $history['id'] }}"
                                            wire:click="openSupportDetailModal({{ $history['id'] }})"
                                            class="hover:bg-blue-50 cursor-pointer transition-colors">
                                            <td class="px-3 py-2">{{ $history['support_date'] }}</td>
                                            <td class="px-3 py-2">{{ $history['support_time'] }}</td>
                                            <td class="px-3 py-2">{{ $history['tr_name'] }}</td>
                                            <td class="px-3 py-2">{{ $history['support_type'] }}</td>
                                            <td class="px-3 py-2 max-w-24 truncate" title="{{ $history['target'] }}">{{ $history['target'] }}</td>
                                            <td class="px-3 py-2 max-w-28 truncate" title="{{ $history['issue'] }}">{{ $history['issue'] }}</td>
                                            <td class="px-3 py-2 max-w-36 truncate" title="{{ $history['to_account'] }}">{{ $history['to_account'] }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                                    {{ $history['status'] === '완료' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                                    {{ $history['status'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-3 py-8 text-center text-gray-400">
                                                최근 10년 지원/소통 이력이 없습니다.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-400">이력 행을 클릭하면 상세 내용을 볼 수 있습니다.</p>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 text-right">
                    @if($isEditingDetail)
                        <button wire:click="cancelDetailEdit"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer mr-2">
                            취소
                        </button>
                        <button wire:click="saveDetailFields"
                                class="px-4 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors cursor-pointer mr-2"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-70 cursor-not-allowed"
                                wire:target="saveDetailFields">
                            <span wire:loading.remove wire:target="saveDetailFields">저장</span>
                            <span wire:loading wire:target="saveDetailFields">저장 중...</span>
                        </button>
                    @else
                        <button wire:click="startDetailEdit"
                                class="px-4 py-2 text-sm text-blue-600 border border-blue-300 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer mr-2">
                            수정
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- 지원/소통 이력 상세 모달 --}}
    @if($showSupportDetailModal && $selectedSupportRecord)
        <div class="mochi-modal-overlay z-[60]"
             wire:click.self="closeSupportDetailModal">
            <div class="mochi-modal-shell max-w-2xl max-h-[78vh] z-[61] flex flex-col"
                 wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">지원 내역 상세</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $selectedSupportRecord['support_date'] ?? '-' }} {{ $selectedSupportRecord['support_time'] ?? '-' }}
                        </p>
                    </div>
                    <button wire:click="closeSupportDetailModal" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 grid grid-cols-2 gap-4 text-sm overflow-y-auto flex-1">
                    <div>
                        <div class="text-xs text-gray-500 mb-1">담당자(TR)</div>
                        <div class="font-medium text-gray-900">{{ $selectedSupportRecord['tr_name'] ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">지원방법</div>
                        <div class="font-medium text-gray-900">{{ $selectedSupportRecord['support_type'] ?? '-' }}</div>
                    </div>

                    <div class="col-span-2">
                        <div class="text-xs text-gray-500 mb-1">참석자</div>
                        <div class="font-medium text-gray-900">{{ $selectedSupportRecord['target'] ?? '-' }}</div>
                    </div>

                    <div class="col-span-2">
                        <div class="text-xs text-gray-500 mb-1">이슈/방문목적</div>
                        <div class="font-medium text-gray-900 whitespace-pre-wrap">{{ $selectedSupportRecord['issue'] ?? '-' }}</div>
                    </div>

                    <div class="col-span-2">
                        <div class="text-xs text-gray-500 mb-1">기관과의 소통내용</div>
                        <div class="font-medium text-gray-900 whitespace-pre-wrap">{{ $selectedSupportRecord['to_account'] ?? '-' }}</div>
                    </div>

                    <div class="col-span-2">
                        <div class="text-xs text-gray-500 mb-1">본사/타 부서 공유 내용</div>
                        <div class="font-medium text-gray-900 whitespace-pre-wrap">{{ $selectedSupportRecord['to_depart'] ?? '-' }}</div>
                    </div>

                    <div class="col-span-2">
                        <div class="text-xs text-gray-500 mb-1">기타</div>
                        <div class="font-medium text-gray-900 whitespace-pre-wrap">{{ $selectedSupportRecord['others'] ?? '-' }}</div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500 mb-1">상태</div>
                        <div class="font-medium text-gray-900">{{ $selectedSupportRecord['status'] ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">완료일</div>
                        <div class="font-medium text-gray-900">{{ $selectedSupportRecord['completed_date'] ?? '-' }}</div>
                    </div>
                </div>

                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-right flex-shrink-0">
                    <button wire:click="closeSupportDetailModal"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                        닫기
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- 담당자 변경 모달 --}}
    @if($showManagerModal)
        <div class="mochi-modal-overlay"
             wire:click.self="closeManagerModal">
            <div class="mochi-modal-shell max-w-xl"
                 wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">담당자 변경</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $editInstitutionName ?: '-' }} ({{ $editSkCode ?: '-' }})
                        </p>
                    </div>
                    <button wire:click="closeManagerModal" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveManagers" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">담당 CO</label>
                        <select wire:model="editCo"
                                class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">미지정</option>
                            @foreach($coManagerOptions as $manager)
                                <option value="{{ $manager }}">{{ $manager }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">담당 TR</label>
                        <select wire:model="editTr"
                                class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">미지정</option>
                            @foreach($trManagerOptions as $manager)
                                <option value="{{ $manager }}">{{ $manager }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">담당 CS</label>
                        <select wire:model="editCs"
                                class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">미지정</option>
                            @foreach($csManagerOptions as $manager)
                                <option value="{{ $manager }}">{{ $manager }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button type="button" wire:click="closeManagerModal"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-70 cursor-not-allowed">
                            <span wire:loading.remove wire:target="saveManagers">저장</span>
                            <span wire:loading wire:target="saveManagers">저장 중...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- 신규 기관 생성 모달 --}}
    @if($showCreateModal)
        <div class="mochi-modal-overlay"
             wire:click.self="closeCreateModal">
            <div class="mochi-modal-shell max-w-2xl"
                 wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50/80 to-white">
                    <h2 class="text-lg font-bold text-gray-900">신규 기관 생성</h2>
                    <button wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveNewInstitution" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">기관명 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="newInstitutionName"
                                   class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('newInstitutionName') ? 'border-red-400' : 'border-gray-300' }}"
                                   placeholder="기관명을 입력하세요" />
                            @error('newInstitutionName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SK코드 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="newSkCode"
                                   class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('newSkCode') ? 'border-red-400' : 'border-gray-300' }}"
                                   placeholder="예: SK9999" />
                            @error('newSkCode') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">구분</label>
                            <select wire:model="newGubun"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">미지정</option>
                                @foreach($gubunList as $gubun)
                                    <option value="{{ $gubun }}">{{ $gubun }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">가능성</label>
                            <select wire:model="newPossibility"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">미선택</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                            @error('newPossibility') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">원장명</label>
                            <input type="text" wire:model="newDirector"
                                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="원장명" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Type</label>
                            <input type="text" wire:model="newCustomerType"
                                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="예: GTS 15 전환" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">GS Number</label>
                            <input type="text" wire:model="newGsNo"
                                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="예: 31" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">대표전화</label>
                            <input type="text" wire:model="newPhone"
                                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="대표 전화번호" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">직통 연락처</label>
                            <input type="text" wire:model="newAccountTel"
                                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="직통 연락처" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                        <input type="text" wire:model="newAddress"
                               class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="기관 주소" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">담당 CO</label>
                            <select wire:model="newCo"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">미지정</option>
                                @foreach($coManagerOptions as $manager)
                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">담당 TR</label>
                            <select wire:model="newTr"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">미지정</option>
                                @foreach($trManagerOptions as $manager)
                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">담당 CS</label>
                            <select wire:model="newCs"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">미지정</option>
                                @foreach($csManagerOptions as $manager)
                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button type="button" wire:click="closeCreateModal"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                            취소
                        </button>
                        <button type="submit"
                                class="px-5 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-70 cursor-not-allowed">
                            <span wire:loading.remove wire:target="saveNewInstitution">저장</span>
                            <span wire:loading wire:target="saveNewInstitution">저장 중...</span>
                        </button>
                    </div>
                </form>
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
