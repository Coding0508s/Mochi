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

    @if(session('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-center gap-2" data-mochi-flash-dismiss="3000" role="alert">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- 상단 요약 영역 (데스크톱: 한 줄 / 모바일: 제목+건수 후 배정 통계) --}}
    <div class="mochi-summary-card">
        <div class="hidden md:flex md:flex-nowrap md:items-center md:gap-4 text-sm">
            <h2 class="shrink-0 text-base font-semibold text-mochi-header">기관리스트</h2>
            <span class="shrink-0 text-gray-300">|</span>
            <button wire:click="$set('assignmentFilter', '')"
                    class="shrink-0 whitespace-nowrap text-gray-600 hover:text-blue-700 transition-colors cursor-pointer">
                배정 전체 <span class="font-semibold text-blue-600">{{ $allInstitutionCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'assigned')"
                    class="shrink-0 whitespace-nowrap text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'assigned' ? 'font-semibold text-green-700' : '' }} cursor-pointer">
                담당자 배정 <span class="font-semibold text-green-600">{{ $assignedCoCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'my_assigned')"
                    class="shrink-0 whitespace-nowrap text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'my_assigned' ? 'font-semibold text-blue-700' : '' }} cursor-pointer">
                내 담당 기관 <span class="font-semibold text-blue-600">{{ $myAssignedCoCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'unassigned')"
                    class="shrink-0 whitespace-nowrap text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'unassigned' ? 'font-semibold text-mochi-header' : '' }} cursor-pointer">
                미배정 <span class="font-semibold text-gray-600">{{ $unassignedCoCount }}</span>
            </button>
            <div class="ml-auto shrink-0 whitespace-nowrap text-gray-500">
                현재 조건 결과: <span class="font-semibold text-gray-700">{{ $institutionTableTotal }}</span>건
            </div>
        </div>

        <div class="flex flex-nowrap items-center gap-2 text-xs md:hidden">
            <h2 class="shrink-0 text-sm font-semibold text-mochi-header">기관리스트</h2>
            <button wire:click="$set('assignmentFilter', '')"
                    class="shrink-0 whitespace-nowrap text-gray-600 hover:text-blue-700 transition-colors cursor-pointer">
                배정 <span class="font-semibold text-blue-600">{{ $allInstitutionCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'assigned')"
                    class="shrink-0 whitespace-nowrap text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'assigned' ? 'font-semibold text-green-700' : '' }} cursor-pointer">
                담당 <span class="font-semibold text-green-600">{{ $assignedCoCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'my_assigned')"
                    class="shrink-0 whitespace-nowrap text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'my_assigned' ? 'font-semibold text-blue-700' : '' }} cursor-pointer">
                내담당 <span class="font-semibold text-blue-600">{{ $myAssignedCoCount }}</span>
            </button>
            <button wire:click="$set('assignmentFilter', 'unassigned')"
                    class="shrink-0 whitespace-nowrap text-gray-600 hover:text-blue-700 transition-colors
                           {{ $assignmentFilter === 'unassigned' ? 'font-semibold text-mochi-header' : '' }} cursor-pointer">
                미배정 <span class="font-semibold text-gray-600">{{ $unassignedCoCount }}</span>
            </button>
            <div class="ml-auto shrink-0 whitespace-nowrap text-gray-500">
                <span class="font-semibold text-gray-700">{{ $institutionTableTotal }}</span>건
            </div>
        </div>
    </div>

    <livewire:institution-filter
        :search="$search"
        :status-filter="$statusFilter"
        :filter-co="$filterCo"
        :filter-tr="$filterTr"
        :filter-cs="$filterCs"
        :assignment-filter="$assignmentFilter"
        :can-view-all-institutions="$canViewAllInstitutions"
        :can-toggle-view-all-institutions="$canToggleViewAllInstitutions"
        :co-manager-options="$coManagerOptions->all()"
        :tr-manager-options="$trManagerOptions->all()"
        :cs-manager-options="$csManagerOptions->all()"
    />

    <livewire:institution-table
        wire:key="institution-table-{{ md5($search.$statusFilter.$assignmentFilter.$filterCo.$filterTr.$filterCs.$sortField.$sortDirection) }}"
        :search="$search"
        :status-filter="$statusFilter"
        :assignment-filter="$assignmentFilter"
        :filter-co="$filterCo"
        :filter-tr="$filterTr"
        :filter-cs="$filterCs"
        :sort-field="$sortField"
        :sort-direction="$sortDirection"
    />

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
                    <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200 pb-2">
                        <button type="button"
                                wire:click="setDetailTab('overview')"
                                @class([
                                    'px-3 py-1.5 text-xs font-medium rounded-lg transition-colors cursor-pointer',
                                    'bg-mochi-header text-white' => ($activeDetailTab ?? 'overview') === 'overview',
                                    'text-gray-600 bg-gray-100 hover:bg-gray-200' => ($activeDetailTab ?? 'overview') !== 'overview',
                                ])>
                            기본 정보
                        </button>
                        <button type="button"
                                wire:click="setDetailTab('timeline')"
                                @class([
                                    'px-3 py-1.5 text-xs font-medium rounded-lg transition-colors cursor-pointer',
                                    'bg-mochi-header text-white' => ($activeDetailTab ?? 'overview') === 'timeline',
                                    'text-gray-600 bg-gray-100 hover:bg-gray-200' => ($activeDetailTab ?? 'overview') !== 'timeline',
                                ])>
                            통합 타임라인
                        </button>
                    </div>

                    @if(($activeDetailTab ?? 'overview') === 'timeline')
                        @include('partials.institution.unified-timeline-section')
                    @else
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

                    @if(! $isEditingDetail)
                        @include('partials.institution.form-detail-readonly-table', ['selectedInstitution' => $selectedInstitution])
                    @endif

                    <div class="col-span-2">
                        <livewire:institution-form-modal
                            embed-mode="detail"
                            wire:key="institution-detail-form-{{ $selectedInstitution['id'] ?? 0 }}"
                            :co-manager-options="$coManagerOptions->values()->all()"
                            :tr-manager-options="$trManagerOptions->values()->all()"
                            :cs-manager-options="$csManagerOptions->values()->all()"
                            :gubun-list="$gubunList->values()->all()"
                            :customer-type-options="$customerTypeOptions->values()->all()"
                        />
                    </div>

                    @include('partials.institution.team-support-history-section')
                    </div>
                    @endif
                </div>

                @if(($activeDetailTab ?? 'overview') === 'overview' && ! $isEditingDetail && ($canEditInstitutionDetail ?? false))
                    <div class="shrink-0 border-t border-gray-200 bg-gray-50 px-4 py-3 text-right sm:px-5">
                        <button wire:click="startDetailEdit"
                                class="px-4 py-2 text-sm text-blue-600 border border-blue-300 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer mr-2">
                            수정
                        </button>
                    </div>
                @endif
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

    <livewire:institution-form-modal
        embed-mode="manager"
        :co-manager-options="$coManagerOptions->values()->all()"
        :tr-manager-options="$trManagerOptions->values()->all()"
        :cs-manager-options="$csManagerOptions->values()->all()"
    />

    <div wire:loading.delay class="fixed bottom-6 right-6 z-50">    <div wire:loading.delay class="fixed bottom-6 right-6 z-50">
        <div class="bg-white rounded-xl px-4 py-3 shadow-lg border border-gray-200 flex items-center gap-2 text-sm text-gray-700">
            <svg class="animate-spin w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            로딩 중...
        </div>
    </div>
</div>
