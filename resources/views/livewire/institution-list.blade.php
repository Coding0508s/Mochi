<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2" data-mochi-flash-dismiss="3000" role="status">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm flex items-center gap-2" data-mochi-flash-dismiss="3000" role="status">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            {{ session('warning') }}
        </div>
    @endif

    {{-- 상단 요약 영역 (잠재기관 페이지와 동일 톤) --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-mochi-header">기관리스트</h2>
           <!--  <span class="text-xs text-gray-500">({{ $statusScopeLabel }} · Information+마스터)</span> -->
            <span class="text-gray-300">|</span>
            <button wire:click="$set('assignmentFilter', '')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer">
                배정 전체 <span class="font-semibold text-blue-600">{{ $allInstitutionCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'assigned')"
                    class="text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'assigned' ? 'font-semibold text-green-700' : '' }} cursor-pointer">
                담당자 배정 <span class="font-semibold text-green-600">{{ $assignedCoCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'my_assigned')"
                    class="text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'my_assigned' ? 'font-semibold text-blue-700' : '' }} cursor-pointer">
                내 담당 기관 <span class="font-semibold text-blue-600">{{ $myAssignedCoCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'unassigned')"
                    class="text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'unassigned' ? 'font-semibold text-mochi-header' : '' }} cursor-pointer">
                미배정 <span class="font-semibold text-gray-600">{{ $unassignedCoCount }}</span>
            </button>
            <div class="ml-auto text-gray-500">
                현재 조건 결과: <span class="font-semibold text-gray-700">{{ $institutions->total() }}</span>건
            </div>
        </div>
    </div>

    {{-- 필터 영역 --}}
    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="statusFilter"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="active">운영 기관</option>
                <option value="terminated">해지 기관</option>
                <option value="all">전체</option>
            </select>

            @if($search || $assignmentFilter || $statusFilter !== 'all')
                <button wire:click="$set('search', ''); $set('assignmentFilter', ''); $set('statusFilter', 'all')"
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
                       placeholder="기관명, SK코드, 원장명, 주소, CO·Coach·CS 검색"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
            </div>
        </div>
    </div>

    {{-- 메인 리스트 테이블 --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto isolate">
            <table class="institution-list-table w-full text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                <tr class="text-gray-700">
                    <th class="institution-sticky-no institution-sticky-no--head px-3 py-2 text-left text-xs font-semibold">No</th>
                    <th class="institution-sticky-sk institution-sticky-sk--head px-3 py-2 text-left text-xs font-semibold">
                        <button wire:click="sort('SKcode')" class="flex items-center gap-1 hover:text-blue-700">
                            SK코드
                            @if($sortField === 'SKcode')
                                <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </button>
                    </th>
                    <th class="institution-sticky-name institution-sticky-name--head px-3 py-2 text-left text-xs font-semibold">
                        <button wire:click="sort('AccountName')" class="flex items-center gap-1 hover:text-blue-700">
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
                        wire:click="openDetailModal({{ $account->ID }})"
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

    {{-- 기관 상세 모달 --}}
    @if($showDetailModal && $selectedInstitution)
        <div class="mochi-modal-overlay"
             wire:click.self="closeDetailModal">
            <div class="mochi-modal-shell flex w-full max-w-2xl max-h-[90vh] flex-col overflow-hidden"
                 wire:click.stop>
                <x-admin.modal-header
                    title="기관 상세 정보"
                    :subtitle="$selectedInstitution['name'] ?? '-'"
                    close-action="closeDetailModal"
                >
                    <x-slot:titleAddon>
                        <span class="inline-flex items-center rounded-full bg-mochi-header/10 px-2 py-0.5 text-xs font-semibold text-mochi-header">
                            {{ $selectedInstitution['skcode'] ?? '-' }}
                        </span>
                    </x-slot:titleAddon>
                </x-admin.modal-header>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 text-sm sm:px-5">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                    <div class="col-span-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs text-gray-500 mb-1">요약</div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                SK {{ $selectedInstitution['skcode'] ?? '-' }}
                            </span>
                            <span class="text-gray-600">담당 CO: <span class="font-medium text-gray-800">{{ $selectedInstitution['co'] ?? '-' }}</span></span>
                            <span class="text-gray-600">담당 Coach: <span class="font-medium text-gray-800">{{ $selectedInstitution['tr'] ?? '-' }}</span></span>
                            <span class="text-gray-600">담당 CS: <span class="font-medium text-gray-800">{{ $selectedInstitution['cs'] ?? '-' }}</span></span>
                            <span class="text-gray-600">교사 수: <span class="font-medium text-gray-800">{{ $selectedInstitution['teacher_count'] ?? 0 }}</span></span>
                            <span class="text-gray-600">지원 내역: <span class="font-medium text-gray-800">{{ $selectedInstitution['support_count'] ?? 0 }}</span>건</span>
                        </div>
                    </div>

                    {{-- 기본정보를 테이블로 압축해 세로 공간을 줄입니다 --}}
                    @php
                        $editDetailCoreFields = $isEditingDetail && ($canEditDetailCore ?? false);
                        $editDetailCoField = $isEditingDetail && ($canEditDetailCo ?? false);
                        $editDetailTrField = $isEditingDetail && ($canEditDetailTr ?? false);
                        $editDetailCsField = $isEditingDetail && ($canEditDetailCs ?? false);
                    @endphp
                    <div class="col-span-2 border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">SKcode</th>
                                    <td class="px-3 py-2 font-mono text-sm text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailSkCode"
                                                   class="w-full py-1.5 px-2 text-sm font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            @error('editDetailSkCode')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            <span class="font-semibold">{{ $selectedInstitution['skcode'] ?? '-' }}</span>
                                        @endif
                                    </td>
                                    <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관명</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailInstitutionName"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            @error('editDetailInstitutionName')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['name'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">영문명</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailEnglishName"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            @error('editDetailEnglishName')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['english_name'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">포털 표시명</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailPortalName"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            @error('editDetailPortalName')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['portal_name'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">Portal Campus ID</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900 font-mono text-sm">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailPortalCampusId"
                                                   class="w-full py-1.5 px-2 text-sm font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            @error('editDetailPortalCampusId')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['portal_campus_id'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">사업자/기관번호</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailAccountNo"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            @error('editDetailAccountNo')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['account_no'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">구분</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailGubun" list="institution-detail-gubun-options"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            <datalist id="institution-detail-gubun-options">
                                                @foreach($gubunList as $gubunOption)
                                                    <option value="{{ $gubunOption }}"></option>
                                                @endforeach
                                            </datalist>
                                            @error('editDetailGubun')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['gubun'] ?? '-' }}
                                        @endif
                                    </td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">고객유형</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <div>
                                                <select wire:model.defer="editCustomerType"
                                                        class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header">
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
                                        @if($editDetailCoreFields)
                                            <div>
                                                <input type="text"
                                                       wire:model.defer="editGsNo"
                                                       placeholder="GS Number 입력"
                                                       class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
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
                                        @if($editDetailCoField)
                                            <select wire:model.defer="editDetailCo"
                                                    class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                                <option value="">미지정</option>
                                                @foreach($coManagerOptions as $manager)
                                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                                @endforeach
                                            </select>
                                            @error('editDetailCo')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            <div>{{ $selectedInstitution['co'] ?? '-' }}</div>
                                            <p class="mt-1 text-[11px] text-gray-400">
                                                최근 변경
                                                @if(! empty($selectedInstitution['co_changed_at']))
                                                    {{ $selectedInstitution['co_changed_at'] }}
                                                    · {{ $selectedInstitution['co_changed_by'] ?? 'Internal Update' }}
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 Coach</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailTrField)
                                            <select wire:model.defer="editDetailTr"
                                                    class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                                <option value="">미지정</option>
                                                @foreach($trManagerOptions as $manager)
                                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                                @endforeach
                                            </select>
                                            @error('editDetailTr')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            <div>{{ $selectedInstitution['tr'] ?? '-' }}</div>
                                            <p class="mt-1 text-[11px] text-gray-400">
                                                최근 변경
                                                @if(! empty($selectedInstitution['tr_changed_at']))
                                                    {{ $selectedInstitution['tr_changed_at'] }}
                                                    · {{ $selectedInstitution['tr_changed_by'] ?? 'Internal Update' }}
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 CS</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCsField)
                                            <select wire:model.defer="editDetailCs"
                                                    class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                                <option value="">미지정</option>
                                                @foreach($csManagerOptions as $manager)
                                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                                @endforeach
                                            </select>
                                            @error('editDetailCs')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            <div>{{ $selectedInstitution['cs'] ?? '-' }}</div>
                                            <p class="mt-1 text-[11px] text-gray-400">
                                                최근 변경
                                                @if(! empty($selectedInstitution['cs_changed_at']))
                                                    {{ $selectedInstitution['cs_changed_at'] }}
                                                    · {{ $selectedInstitution['cs_changed_by'] ?? 'Internal Update' }}
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">원장명</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailDirector"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            @error('editDetailDirector')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['director'] ?? '-' }}
                                        @endif
                                    </td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">대표전화</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailPhone"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            @error('editDetailPhone')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['phone'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">직통 연락처</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailAccountTel"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                                            @error('editDetailAccountTel')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['account_tel'] ?? '-' }}
                                        @endif
                                    </td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">최근 지원일</th>
                                    <td class="px-3 py-2 font-medium text-gray-500">
                                        {{ $selectedInstitution['latest_support_date'] ? substr((string) $selectedInstitution['latest_support_date'], 0, 10) : '-' }}
                                        @if($editDetailCoreFields)
                                            <p class="mt-1 text-[11px] text-gray-400">지원 이력에서 자동 집계됩니다.</p>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">주소</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <textarea wire:model.defer="editDetailAddress" rows="2"
                                                      class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header"></textarea>
                                            @error('editDetailAddress')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        @else
                                            {{ $selectedInstitution['address'] ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- 최근 10년 지원/소통 이력 --}}
                    @php
                        $detailCustomerType = (string) ($selectedInstitution['customer_type'] ?? '');
                        $detailIsTerminated = str_contains($detailCustomerType, '해지');
                        $detailSkCode = trim((string) ($selectedInstitution['skcode'] ?? ''));
                    @endphp
                    <div class="col-span-2 mt-2">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <h3 class="text-base font-bold text-[#1f4f8f] flex items-center gap-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-mochi-header"></span>
                                최근 10년 지원/소통 이력
                            </h3>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                                    총 {{ count($supportHistory) }}건
                                </span>
                                @if($detailSkCode !== '' && ! $detailIsTerminated)
                                    <a href="{{ route('supports.create', ['sk_code' => $detailSkCode, 'return' => 'institutions']) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-mochi-header rounded-lg hover:bg-mochi-header/90">
                                        지원보고서 작성
                                    </a>
                                @elseif($detailSkCode !== '' && $detailIsTerminated)
                                    <span class="text-xs text-gray-500">해지 기관은 신규 지원보고서 작성이 제한됩니다.</span>
                                @endif
                            </div>
                        </div>
                        @if($detailSkCode !== '' && ! $detailIsTerminated)
                            <p class="text-xs text-gray-500 mb-2">작성 화면으로 이동합니다. SK·기관명이 자동으로 채워집니다.</p>
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
                                        <th class="px-3 py-2 text-left">참석자</th>
                                        <th class="px-3 py-2 text-left">이슈</th>
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
                                                <span class="text-[11px] {{ $history['status'] === '완료' ? 'text-green-700' : 'text-gray-600' }}">
                                                    {{ $history['status'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-3 py-8 text-center text-gray-400">
                                                <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
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
                </div>

                <div class="shrink-0 border-t border-gray-200 bg-gray-50 px-4 py-3 text-right sm:px-5">
                    @if($isEditingDetail)
                        <button wire:click="cancelDetailEdit"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer mr-2">
                            취소
                        </button>
                        <button wire:click="saveDetailFields"
                                class="px-4 py-2 text-sm text-white bg-mochi-header hover:bg-mochi-header/90 rounded-lg transition-colors cursor-pointer mr-2"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-70 cursor-not-allowed"
                                wire:target="saveDetailFields">
                            <span wire:loading.remove wire:target="saveDetailFields">저장</span>
                            <span wire:loading wire:target="saveDetailFields">저장 중...</span>
                        </button>
                    @elseif($canEditInstitutionDetail ?? false)
                        <button wire:click="startDetailEdit"
                                class="px-4 py-2 text-sm text-blue-600 border border-blue-300 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer mr-2">
                            수정
                        </button>
                    @endif
                    @error('detailEdit')
                        <p class="mt-2 text-sm text-red-600 text-left">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    @endif

    {{-- 지원/소통 이력 상세 모달 --}}
    <x-institution.support-detail-modal
        :show="$showSupportDetailModal"
        :selected-support-record="$selectedSupportRecord"
        :selected-institution="$selectedInstitution"
        :support-detail-edit-mode="$supportDetailEditMode"
    />

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
                        @if($canEditDetailCo ?? false)
                            <select wire:model="editCo"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                <option value="">미지정</option>
                                @foreach($coManagerOptions as $manager)
                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="py-2 px-3 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg">{{ $editCo ?: '-' }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">담당 Coach</label>
                        @if($canEditDetailTr ?? false)
                            <select wire:model="editTr"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                <option value="">미지정</option>
                                @foreach($trManagerOptions as $manager)
                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="py-2 px-3 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg">{{ $editTr ?: '-' }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">담당 CS</label>
                        @if($canEditDetailCs ?? false)
                            <select wire:model="editCs"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                <option value="">미지정</option>
                                @foreach($csManagerOptions as $manager)
                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="py-2 px-3 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg">{{ $editCs ?: '-' }}</p>
                        @endif
                    </div>

                    @error('managerEdit')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button type="button" wire:click="closeManagerModal"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm text-white bg-mochi-header hover:bg-mochi-header/90 rounded-lg transition-colors"
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
