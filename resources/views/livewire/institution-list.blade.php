<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm" data-mochi-flash-dismiss="3000" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm" data-mochi-flash-dismiss="3000" role="status">
            {{ session('warning') }}
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
            <button wire:click="$set('assignmentFilter', 'my_assigned')"
                    class="text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'my_assigned' ? 'font-semibold text-blue-700' : '' }} cursor-pointer">
                내 담당 기관 <span class="font-semibold text-blue-600">{{ $myAssignedCoCount }}</span>
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
            <select wire:model.live="statusFilter"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="active">운영 기관</option>
                <option value="terminated">해지 기관</option>
                <option value="all">전체</option>
            </select>

            @if($search || $assignmentFilter || $statusFilter !== 'active')
                <button wire:click="$set('search', ''); $set('assignmentFilter', ''); $set('statusFilter', 'active')"
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
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                    <th class="px-3 py-2 text-left text-xs font-semibold">Coach</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">CS</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">GS번호</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">구분</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">Type</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">기관장</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">연락처</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">기관연락처</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">주소</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($institutions as $index => $inst)
                    @php
                        $customerType = (string) ($inst->accountInfo?->Customer_Type ?? '');
                        $isTerminated = str_contains($customerType, '해지');
                        $customerTypeWithoutTerminateBadge = $customerType;
                        if ($isTerminated) {
                            $customerTypeWithoutTerminateBadge = trim((string) preg_replace('/^해지$/u', '', $customerTypeWithoutTerminateBadge));
                            $customerTypeWithoutTerminateBadge = trim((string) preg_replace('/^해지\s+/u', '', $customerTypeWithoutTerminateBadge));
                            $customerTypeWithoutTerminateBadge = trim((string) preg_replace('/\s+해지$/u', '', $customerTypeWithoutTerminateBadge));
                            $customerTypeWithoutTerminateBadge = trim((string) preg_replace('/\s+/u', ' ', $customerTypeWithoutTerminateBadge));
                        }
                    @endphp
                    <tr wire:key="institution-row-{{ $inst->ID }}"
                        wire:click="openDetailModal({{ $inst->ID }})"
                        class="mochi-table-row-hover transition-colors cursor-pointer">
                        <td class="institution-sticky-no px-3 py-2 text-gray-500 text-xs">{{ $institutions->firstItem() + $index }}</td>
                        <td class="institution-sticky-sk px-3 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                {{ $inst->SKcode ?? '-' }}
                            </span>
                        </td>
                        <td class="institution-sticky-name px-3 py-2 font-medium text-gray-900">
                            {{ $inst->resolvedAccountName() ?: '-' }}
                            @if($inst->EnglishName)
                                <span class="block text-xs text-gray-400">{{ $inst->EnglishName }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $inst->accountInfo?->CO ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $inst->accountInfo?->TR ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $inst->accountInfo?->CS ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-600 font-mono text-xs">{{ $inst->resolvedGsNumber() ?: '-' }}</td>
                        <td class="px-3 py-2">
                            @if($inst->Gubun)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    {{ $inst->Gubun }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
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
                        <td class="px-3 py-2 text-gray-600">{{ $inst->Director ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $inst->Phone ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $inst->AccountTel ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-500 max-w-56 truncate" title="{{ $inst->Address }}">
                            {{ $inst->Address ?? '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="px-4 py-16 text-center text-gray-400">
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
                                                   class="w-full py-1.5 px-2 text-sm font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                                   class="w-full py-1.5 px-2 text-sm font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                        @if($editDetailCoreFields)
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
                                        @if($editDetailCoField)
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
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 Coach</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailTrField)
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
                                        @if($editDetailCsField)
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
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        @if($editDetailCoreFields)
                                            <input type="text" wire:model.defer="editDetailDirector"
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                                   class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                                                      class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
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
                                <span class="inline-block w-2 h-2 rounded-full bg-[#2b78c5]"></span>
                                최근 10년 지원/소통 이력
                            </h3>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                                    총 {{ count($supportHistory) }}건
                                </span>
                                @if($detailSkCode !== '' && ! $detailIsTerminated)
                                    <a href="{{ route('supports.create', ['sk_code' => $detailSkCode, 'return' => 'institutions']) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
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
                </div>

                <div class="shrink-0 border-t border-gray-200 bg-gray-50 px-4 py-3 text-right sm:px-5">
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
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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
