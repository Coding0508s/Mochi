<div class="mochi-page" x-data @shared-supply-show-alert.window="alert($event.detail.message)">
    @if(session('success'))
        <div class="px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm" data-mochi-flash-dismiss="3000" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <h2 class="text-base font-semibold text-[#2b78c5]">공용품관리</h2>
            <span class="hidden sm:inline text-gray-300" aria-hidden="true">|</span>
            <div class="mochi-toggle-group flex-wrap">
                <button type="button"
                        wire:click="toggleReservationView('reservation')"
                        class="mochi-toggle-btn {{ $reservationView === 'reservation' ? 'mochi-toggle-btn--active !bg-indigo-600 !text-white !border-indigo-600' : '' }}">
                    예약형(공용품)
                </button>
                <button type="button"
                        wire:click="toggleReservationView('personal')"
                        class="mochi-toggle-btn {{ $reservationView === 'personal' ? 'mochi-toggle-btn--active !bg-emerald-600 !text-white !border-emerald-600' : '' }}">
                    일반(개인 일정)
                </button>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-gray-100 pt-3">
            <div class="grid w-full grid-cols-1 gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2 sm:flex sm:w-auto sm:items-center sm:gap-1">
                <input type="date"
                       wire:model.live="dateFrom"
                       class="h-9 w-full min-w-0 rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:h-8 sm:w-auto">
                <span class="hidden text-center text-sm text-gray-500 sm:inline">~</span>
                <span class="text-center text-xs text-gray-500 sm:hidden">~</span>
                <input type="date"
                       wire:model.live="dateTo"
                       class="h-9 w-full min-w-0 rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:h-8 sm:w-auto">
            </div>

            <input type="text"
                   wire:model.defer="search"
                   wire:keydown.enter.prevent="applySearch"
                   placeholder="라벨 / 공용품코드 / 물품명 / 사용자명 / 제목 검색"
                   class="w-full min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 sm:min-w-[12rem] lg:max-w-md">

            <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:shrink-0">
                <button type="button"
                        wire:click="applySearch"
                        class="rounded-lg border border-blue-200 px-3 py-2 text-sm text-blue-700 hover:bg-blue-50">
                    검색
                </button>
                <button type="button"
                        wire:click="openCreateModal"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">
                    일정 등록
                </button>
            </div>
        </div>
        @if (auth()->user()?->hasFullAccess())
            <div class="mt-3 flex flex-col gap-2 border-t border-gray-100 pt-3 sm:flex-row sm:flex-wrap sm:items-center">
                <input type="file"
                       wire:model="importFile"
                       accept=".xls,.xlsx"
                       class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm sm:w-auto sm:max-w-xs">
                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                    <button type="button"
                            wire:click="importFromExcel"
                            class="rounded-lg border border-indigo-200 px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50">
                        엑셀 업로드
                    </button>
                    <button type="button"
                            wire:click="openResetModal"
                            class="rounded-lg border border-rose-300 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50">
                        초기화 실행
                    </button>
                </div>
            </div>
        @endif
        @error('importFile') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

        @if($importNotice !== null || $importSummary !== null || $importErrors !== [])
            <div x-data
                 x-init="window.setTimeout(() => {
                     $wire.set('importNotice', null);
                     $wire.set('importSummary', null);
                     $wire.set('importErrors', []);
                 }, 3000)">
                @if($importNotice !== null)
                    <div class="mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800"
                         data-mochi-flash-dismiss="3000"
                         role="status">
                        {{ $importNotice }}
                    </div>
                @endif

                @if($importSummary !== null)
                    <div class="mt-3 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm text-indigo-800"
                         data-mochi-flash-dismiss="3000"
                         role="status">
                        신규 {{ $importSummary['inserted'] }}건 · 업데이트 {{ $importSummary['updated'] }}건 · 삭제 {{ $importSummary['deleted'] ?? 0 }}건 · 건너뜀 {{ $importSummary['skipped'] }}건
                    </div>
                @endif

                @if($importErrors !== [])
                    <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 space-y-1"
                         data-mochi-flash-dismiss="3000"
                         role="alert">
                        @foreach(array_slice($importErrors, 0, 10) as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                        @if(count($importErrors) > 10)
                            <p>...외 {{ count($importErrors) - 10 }}건</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="mochi-table-card overflow-hidden">
        @if($activeTab === 'monthly')
            <div class="grid gap-3 p-4 grid-cols-2 md:grid-cols-4">
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                    <p class="text-xs text-blue-700">총 일정</p>
                    <p class="mt-1 text-xl font-semibold text-blue-900">{{ $monthlySummary['total_count'] }}</p>
                </div>
                <div class="rounded-lg border border-indigo-100 bg-indigo-50 p-3">
                    <p class="text-xs text-indigo-700">사용자 수</p>
                    <p class="mt-1 text-xl font-semibold text-indigo-900">{{ $monthlySummary['user_count'] }}</p>
                </div>
                <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                    <p class="text-xs text-emerald-700">공용품 수</p>
                    <p class="mt-1 text-xl font-semibold text-emerald-900">{{ $monthlySummary['item_count'] }}</p>
                </div>
                <div class="rounded-lg border border-violet-100 bg-violet-50 p-3">
                    <p class="text-xs text-violet-700">예약형 제목 건수</p>
                    <p class="mt-1 text-xl font-semibold text-violet-900">{{ $monthlySummary['reservation_count'] }}</p>
                </div>
            </div>
            <div class="overflow-x-auto border-t border-gray-100">
                <table class="min-w-full text-sm">
                    <thead class="mochi-table-head text-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left">일정구분</th>
                            <th class="px-3 py-2 text-right">건수</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($monthlyByCategory as $category)
                            <tr>
                                <td class="px-3 py-2">{{ $category['code'] !== '' ? $category['code'].' - ' : '' }}{{ $category['label'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $category['count'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-3 py-8 text-center text-sm text-gray-400">등록된 공용품 사용 내역이 없습니다.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($activeTab === 'daily')
            <div class="space-y-4 p-4">
                @forelse($dailyGroups as $dateKey => $groupedSupplies)
                    <div class="rounded-lg border border-gray-200">
                        <div class="border-b border-gray-100 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">
                            {{ \Carbon\Carbon::parse($dateKey)->format('Y/m/d (D)') }} · {{ $groupedSupplies->count() }}건
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left">시작시간</th>
                                        <th class="px-3 py-2 text-left">종료시간</th>
                                        <th class="px-3 py-2 text-left">제목</th>
                                        <th class="px-3 py-2 text-left">물품명</th>
                                        <th class="px-3 py-2 text-left">사용자명</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($groupedSupplies as $supply)
                                        <tr wire:key="daily-supply-row-{{ $supply->id }}">
                                            <td class="px-3 py-2">{{ $supply->starts_at->format('H:i') }}</td>
                                            <td class="px-3 py-2">{{ $supply->ends_at->format('H:i') }}</td>
                                            <td class="px-3 py-2">{{ $supply->title }}</td>
                                            <td class="px-3 py-2">{{ $supply->item?->name ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $supply->user?->name ?? 'User' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="px-3 py-8 text-center text-sm text-gray-400">등록된 공용품 사용 내역이 없습니다.</p>
                @endforelse
            </div>
        @elseif($activeTab === 'item')
            <div class="space-y-4 p-4">
                @forelse($itemGroups as $itemName => $groupedSupplies)
                    <div class="rounded-lg border border-gray-200">
                        <div class="border-b border-gray-100 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">
                            {{ $itemName }} · {{ $groupedSupplies->count() }}건
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left">일자</th>
                                        <th class="px-3 py-2 text-left">시간</th>
                                        <th class="px-3 py-2 text-left">제목</th>
                                        <th class="px-3 py-2 text-left">사용자명</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($groupedSupplies as $supply)
                                        <tr wire:key="item-supply-row-{{ $supply->id }}">
                                            <td class="px-3 py-2">{{ $supply->starts_at->format('Y/m/d (D)') }}</td>
                                            <td class="px-3 py-2">{{ $supply->starts_at->format('H:i') }} ~ {{ $supply->ends_at->format('H:i') }}</td>
                                            <td class="px-3 py-2">{{ $supply->title }}</td>
                                            <td class="px-3 py-2">{{ $supply->user?->name ?? 'User' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="px-3 py-8 text-center text-sm text-gray-400">등록된 공용품 사용 내역이 없습니다.</p>
                @endforelse
            </div>
        @else
            <div class="md:hidden space-y-3 p-3">
                @forelse($supplies as $supply)
                    @php
                        $reservationCategoryBadge = $supply->reservationCategoryBadgeLabel();
                        $vehicleRowStatus = $supply->vehicleRowStatus();
                        $vehiclePrimaryRemark = $vehicleRowStatus !== null ? $supply->vehicleRowPrimaryRemark() : '';
                        $vehicleSecondaryRemark = $vehicleRowStatus !== null ? $supply->vehicleRowSecondaryRemark() : '';
                    @endphp
                    <button type="button"
                            wire:key="shared-supply-mobile-{{ $activeTab }}-{{ $supply->id }}"
                            wire:click="openEditModal({{ $supply->id }})"
                            class="w-full rounded-lg border border-gray-200 bg-white p-3 text-left transition hover:border-blue-200 hover:bg-blue-50/30">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-semibold text-gray-600">{{ $supply->starts_at->format('Y/m/d (D)') }}</p>
                            <p class="shrink-0 text-xs text-gray-500">{{ $supply->starts_at->format('H:i') }} ~ {{ $supply->ends_at->format('H:i') }}</p>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span class="font-medium text-sm text-gray-900">{{ $supply->title }}</span>
                            @if($reservationCategoryBadge === '차량 배차')
                                <span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">차량 배차</span>
                            @elseif($reservationCategoryBadge === '회의실')
                                <span class="rounded-md bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700">회의실</span>
                            @endif
                            @if($vehicleRowStatus === 'pending_post_use')
                                <span class="rounded-md bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-700">예약 및 사용중</span>
                            @elseif($vehicleRowStatus === 'complete')
                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">사용 완료</span>
                            @endif
                        </div>
                        <dl class="mt-2 grid grid-cols-[4.5rem_1fr] gap-x-2 gap-y-1 text-xs">
                            <dt class="text-gray-500">물품</dt>
                            <dd class="font-medium text-gray-800">{{ $supply->item?->name ?? '-' }}</dd>
                            <dt class="text-gray-500">사용자</dt>
                            <dd class="font-medium text-gray-800">{{ $supply->user?->name ?? 'User' }}</dd>
                            <dt class="text-gray-500">적요</dt>
                            <dd class="text-gray-700">
                                @if($vehicleRowStatus !== null)
                                    <span class="font-medium text-gray-800">{{ $vehiclePrimaryRemark !== '' ? $vehiclePrimaryRemark : '-' }}</span>
                                    @if($vehicleSecondaryRemark !== '')
                                        <span class="mt-0.5 block text-gray-500">{{ $vehicleSecondaryRemark }}</span>
                                    @endif
                                @else
                                    {{ $supply->purpose !== '' ? $supply->purpose : '-' }}
                                @endif
                            </dd>
                        </dl>
                    </button>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-400">
                        등록된 공용품 사용 내역이 없습니다.
                    </div>
                @endforelse
            </div>

            <div class="hidden md:block overflow-x-auto overflow-y-auto max-h-[60vh] lg:max-h-[70vh]">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="mochi-table-head text-gray-700 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2 text-left whitespace-nowrap">일자</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">시간</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap min-w-[10rem]">제목</th>
                            <th class="hidden lg:table-cell px-3 py-2 text-left whitespace-nowrap">물품 및 일정명</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">사용자명</th>
                            <th class="hidden xl:table-cell px-3 py-2 text-left whitespace-nowrap">적요</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $dateRowspans = [];
                            if ($activeTab === 'user') {
                                foreach ($supplies as $supplyForRowspan) {
                                    $dateKey = $supplyForRowspan->starts_at->toDateString();
                                    $dateRowspans[$dateKey] = ($dateRowspans[$dateKey] ?? 0) + 1;
                                }
                            }
                            $renderedDateKeys = [];
                            $previousDateKey = null;
                        @endphp
                        @forelse($supplies as $supply)
                            @php
                                $dateKey = $supply->starts_at->toDateString();
                                $isFirstRowOfDateGroup = $activeTab === 'user'
                                    ? ! isset($renderedDateKeys[$dateKey])
                                    : ($previousDateKey === null || $previousDateKey !== $dateKey);
                                $showDateSeparator = $previousDateKey !== null && $isFirstRowOfDateGroup;
                                $dateSeparatorClass = $showDateSeparator ? 'border-t border-gray-200' : '';
                                $previousDateKey = $dateKey;
                                $reservationCategoryBadge = $supply->reservationCategoryBadgeLabel();
                                $vehicleRowStatus = $supply->vehicleRowStatus();
                                $vehiclePrimaryRemark = $vehicleRowStatus !== null ? $supply->vehicleRowPrimaryRemark() : '';
                                $vehicleSecondaryRemark = $vehicleRowStatus !== null ? $supply->vehicleRowSecondaryRemark() : '';
                            @endphp
                            <tr wire:key="shared-supply-row-{{ $activeTab }}-{{ $supply->id }}" wire:click="openEditModal({{ $supply->id }})" class="cursor-pointer hover:bg-blue-50/30">
                                @if($activeTab === 'user')
                                    @if($isFirstRowOfDateGroup)
                                        <td rowspan="{{ $dateRowspans[$dateKey] }}" class="px-3 py-2 align-middle whitespace-nowrap bg-gray-50 text-gray-700 font-semibold border-r border-gray-100 {{ $dateSeparatorClass }}">
                                            {{ $supply->starts_at->format('Y/m/d (D)') }}
                                        </td>
                                        @php $renderedDateKeys[$dateKey] = true; @endphp
                                    @endif
                                @else
                                    <td class="px-3 py-2 whitespace-nowrap {{ $dateSeparatorClass }}">{{ $supply->starts_at->format('Y/m/d (D)') }}</td>
                                @endif
                                <td class="px-3 py-2 text-gray-700 {{ $dateSeparatorClass }}">{{ $supply->starts_at->format('H:i') }} ~ {{ $supply->ends_at->format('H:i') }}</td>
                                <td class="px-3 py-2 {{ $dateSeparatorClass }}">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-medium text-gray-900">{{ $supply->title }}</span>
                                        @if($reservationCategoryBadge === '차량 배차')
                                            <span class="shrink-0 rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">차량 배차</span>
                                        @elseif($reservationCategoryBadge === '회의실')
                                            <span class="shrink-0 rounded-md bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700">회의실</span>
                                        @endif
                                        @if($vehicleRowStatus === 'pending_post_use')
                                            <span class="shrink-0 rounded-md bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-700">예약 및 사용중</span>
                                        @elseif($vehicleRowStatus === 'complete')
                                            <span class="shrink-0 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">사용 완료</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 lg:hidden">{{ $supply->item?->name ?? '-' }}</p>
                                </td>
                                <td class="hidden lg:table-cell px-3 py-2 font-medium text-gray-800 {{ $dateSeparatorClass }}">{{ $supply->item?->name ?? '-' }}</td>
                                <td class="px-3 py-2 font-medium text-gray-800 {{ $dateSeparatorClass }}">{{ $supply->user?->name ?? 'User' }}</td>
                                <td class="hidden xl:table-cell px-3 py-2 text-sm {{ $dateSeparatorClass }}">
                                    @if($vehicleRowStatus !== null)
                                        <div class="font-medium text-gray-800">{{ $vehiclePrimaryRemark !== '' ? $vehiclePrimaryRemark : '-' }}</div>
                                        @if($vehicleSecondaryRemark !== '')
                                            <div class="mt-0.5 text-xs text-gray-500">{{ $vehicleSecondaryRemark }}</div>
                                        @endif
                                    @else
                                        <span class="text-gray-500">{{ $supply->purpose }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-400">등록된 공용품 사용 내역이 없습니다.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($hasMoreSupplies)
                <div class="border-t border-gray-100 px-4 py-2">
                    <div wire:intersect.margin.200px="loadMoreSupplies"
                         class="flex h-8 items-center justify-center text-xs text-gray-500">
                        <span wire:loading.remove wire:target="loadMoreSupplies">아래로 스크롤하면 계속 불러옵니다</span>
                        <span wire:loading.inline-flex wire:target="loadMoreSupplies" class="items-center gap-2 text-blue-600">
                            <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                            </svg>
                            더 불러오는 중...
                        </span>
                    </div>
                </div>
            @elseif($supplies->isNotEmpty())
                <div class="border-t border-gray-100 px-4 py-2">
                    <div class="flex h-8 items-center justify-center text-xs text-gray-500">
                        모든 내역을 불러왔습니다
                    </div>
                </div>
            @endif
        @endif
    </div>

    @if($showFormModal)
        <div class="mochi-modal-overlay" wire:click.self="closeFormModal">
            <div class="mochi-modal-shell mx-3 flex w-full max-w-2xl max-h-[min(90vh,calc(100dvh-2rem))] min-h-0 flex-col sm:mx-0" wire:click.stop>
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-6 sm:py-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $viewOnly ? '공용품 사용 상세' : ($editingSupplyId ? '공용품 사용 수정' : '공용품 사용 등록') }}</h3>
                    <button type="button" wire:click="closeFormModal" class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">✕</button>
                </div>

                <form wire:submit.prevent="save" class="flex min-h-0 flex-1 flex-col">
                    <div class="mochi-modal-body-scroll space-y-4 px-4 py-4 sm:px-6 sm:py-5">
                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">일자</label>
                            <input type="date" wire:model.defer="useDate" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
                            @error('useDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">시작시간</label>
                            <input type="time" wire:model.defer="startTime" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
                            @error('startTime') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">종료시간</label>
                            <input type="time" wire:model.defer="endTime" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
                            @error('endTime') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">제목</label>
                            <select wire:model.live="title" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
                                <option value="">선택하세요</option>
                                @foreach($titleOptions as $titleOption)
                                    <option value="{{ $titleOption }}">{{ $titleOption }}</option>
                                @endforeach
                            </select>
                            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">공용품코드리스트</label>
                            <select wire:model.live="sharedSupplyItemId" @disabled($viewOnly || $title === '') class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
                                <option value="">선택하세요</option>
                                @foreach($supplyItems as $item)
                                    <option value="{{ $item->id }}">{{ $item->code }} - {{ $item->name }}</option>
                                @endforeach
                            </select>
                            @error('sharedSupplyItemId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if(str_contains($title, '차량배차'))
                        <div class="rounded-lg border border-blue-100 bg-blue-50/40 p-4">
                            <p class="text-sm font-semibold text-blue-900">차량 운행 기록</p>
                            <p class="mt-1 text-xs text-blue-700">차량 배차 일정일 때만 입력합니다. (모달 전용 입력)</p>

                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">사용자명</label>
                                    <input type="text"
                                           value="{{ auth()->user()?->name ?? '' }}"
                                           readonly
                                           class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-700">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">이동수단명</label>
                                    <input type="text"
                                           value="{{ $supplyItems->firstWhere('id', $sharedSupplyItemId)?->name ?? '' }}"
                                           readonly
                                           class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-700">
                                </div>
                            </div>

                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">사용목적명</label>
                                    <select wire:model.defer="vehicleUsagePurpose"
                                            @disabled($viewOnly)
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
                                        <option value="">선택하세요</option>
                                        @foreach($vehicleUsagePurposeSelectOptions as $purposeValue => $purposeLabel)
                                            <option value="{{ $purposeValue }}">{{ $purposeLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('vehicleUsagePurpose') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">운행거리</label>
                                    <input type="text"
                                           value="{{ ($vehicleOdometerBefore !== null && $vehicleOdometerAfter !== null) ? max(0, $vehicleOdometerAfter - $vehicleOdometerBefore) : '' }}"
                                           readonly
                                           class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-700">
                                </div>
                            </div>

                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">주행전 계기판거리</label>
                                    <input type="number"
                                           min="0"
                                           wire:model.defer="vehicleOdometerBefore"
                                           @disabled($viewOnly)
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
                                    @error('vehicleOdometerBefore') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <span>주행후 계기판거리</span>
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">사용 후 입력</span>
                                    </label>
                                    <input type="number"
                                           min="0"
                                           wire:model.defer="vehicleOdometerAfter"
                                           @disabled($viewOnly || $editingSupplyId === null)
                                           placeholder="방문 후 입력"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
                                    @error('vehicleOdometerAfter') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="mb-1 block text-sm font-medium text-gray-700">해당 차량 최근 적요 (참고)</label>
                                <p class="mb-1 text-xs text-gray-500">도착 위치 / 사유 형식으로 표시됩니다.</p>
                                <textarea rows="1"
                                          readonly
                                          class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-700">{{ $vehicleLatestRemark }}</textarea>
                            </div>

                            <div class="mt-3">
                                <label class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <span>도착 위치</span>
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">사용 후 입력</span>
                                </label>
                                <input type="text"
                                       wire:model.defer="vehicleArrivalLocation"
                                       @disabled($viewOnly || $editingSupplyId === null)
                                       placeholder="방문 후 입력"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
                                @error('vehicleArrivalLocation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            @if($editingSupplyId === null)
                                <p class="mt-2 text-xs text-gray-600">
                                    주행전 계기판거리는 신규 등록 시 최근 차량 운행기록 기준으로 자동 입력됩니다.
                                    주행후 계기판거리는 방문(운행) 후 수정에서 입력해도 됩니다.
                                </p>
                            @endif
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">사유</label>
                        <textarea wire:model.defer="purpose" rows="3" @disabled($viewOnly) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100"></textarea>
                        @error('purpose') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    </div>

                    <div class="flex shrink-0 flex-col-reverse gap-2 border-t border-gray-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:py-4">
                        <div class="flex justify-start">
                            @if($editingSupplyId && ! $viewOnly)
                                <button type="button" wire:click="delete" wire:confirm="이 내역을 삭제할까요?" class="w-full rounded-lg border border-red-200 px-4 py-2 text-sm text-red-600 hover:bg-red-50 sm:w-auto">삭제</button>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:flex sm:gap-2">
                            <button type="button" wire:click="closeFormModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">닫기</button>
                            @unless($viewOnly)
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">저장</button>
                            @endunless
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showResetModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4" wire:click.self="closeResetModal">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">공용품 데이터 초기화</h3>
                    <button type="button" wire:click="closeResetModal" class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">✕</button>
                </div>

                <div class="space-y-4 px-6 py-5">
                    <p class="text-sm text-rose-700">
                        초기화 시 공용품 사용 이력/차량기록/연결된 팀일정/매핑 데이터가 삭제됩니다.
                        (기본 공용품 코드/라벨 마스터는 다시 생성됩니다.)
                    </p>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">확인 문구 입력</label>
                        <input type="text"
                               wire:model.defer="resetConfirmationText"
                               placeholder="초기화 실행"
                               class="w-full rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm">
                        @error('resetConfirmationText') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4">
                        <button type="button" wire:click="closeResetModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">취소</button>
                        <button type="button"
                                wire:click="resetSharedSupplyData"
                                wire:confirm="정말 초기화할까요? 이 작업은 되돌릴 수 없습니다."
                                class="rounded-lg border border-rose-300 px-4 py-2 text-sm text-rose-700 hover:bg-rose-50">
                            초기화 실행
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
