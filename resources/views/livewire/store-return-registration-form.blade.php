<div class="mochi-page space-y-4">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
             data-mochi-flash-dismiss="3000"
             role="status">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
             data-mochi-flash-dismiss="5000"
             role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-mochi-header">반품 현황</h2>
                <p class="mt-1 text-xs text-gray-600">
                    @if($this->isCsTeamMenu)
                        반품 내역을 조회하고 처리 완료합니다.
                    @else
                        물류 팀 반품 내역을 등록하고 조회합니다.
                    @endif
                </p>
            </div>
            @unless($this->isCsTeamMenu)
            <div class="flex flex-wrap items-center gap-2">
                @can('manageStoreReturnProducts')
                    <a href="{{ route('store.returns.products.index', array_filter(['team_menu' => $teamMenu])) }}"
                       class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        반품 품목 관리
                    </a>
                @endcan
                <button type="button"
                        wire:click="openCreateModal"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    반품 등록
                </button>
            </div>
            @endunless
        </div>
    </div>

    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <label for="return-search" class="mb-1 block text-xs font-medium text-gray-600">검색</label>
                <input id="return-search"
                       type="search"
                       wire:model.live.debounce.300ms="search"
                       placeholder="기관명, 품목명, SK 코드, 특이 사항, 담당 CS"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
            </div>
            <div>
                <span class="mb-1 block text-xs font-medium text-gray-600">상태</span>
                <div class="mochi-toggle-group">
                    <button type="button"
                            wire:click="$set('statusFilter', 'all')"
                            aria-pressed="{{ $statusFilter === 'all' ? 'true' : 'false' }}"
                            class="mochi-toggle-btn {{ $statusFilter === 'all' ? 'mochi-toggle-btn--active' : '' }}">
                        전체
                    </button>
                    <button type="button"
                            wire:click="$set('statusFilter', 'in_progress')"
                            aria-pressed="{{ $statusFilter === 'in_progress' ? 'true' : 'false' }}"
                            class="mochi-toggle-btn {{ $statusFilter === 'in_progress' ? 'mochi-toggle-btn--active' : '' }}">
                        진행 중
                    </button>
                    <button type="button"
                            wire:click="$set('statusFilter', 'completed')"
                            aria-pressed="{{ $statusFilter === 'completed' ? 'true' : 'false' }}"
                            class="mochi-toggle-btn {{ $statusFilter === 'completed' ? 'mochi-toggle-btn--active' : '' }}">
                        완료
                    </button>
                </div>
            </div>
            <div class="min-w-[120px]">
                <label for="return-per-page" class="mb-1 block text-xs font-medium text-gray-600">페이지당</label>
                <select id="return-per-page"
                        wire:model.live="perPage"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    <div class="mochi-table-card">
        <div class="overflow-x-auto isolate">
            <table class="w-full min-w-[1040px] table-fixed text-sm">
                <colgroup>
                    <col class="w-[7.5rem]">
                    <col class="w-[12rem]">
                    <col class="w-[8.5rem]">
                    <col class="w-[4rem]">
                    <col class="w-[5.5rem]">
                    <col class="w-[4.5rem]">
                    <col class="w-[8rem]">
                    <col class="w-[6.5rem]">
                    @if($this->isCsTeamMenu)
                        <col class="w-[5.5rem]">
                    @endif
                    @if($this->canDeleteReturnGroups)
                        <col class="w-[4.5rem]">
                    @endif
                </colgroup>
                <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="px-3 py-2 text-center text-xs font-semibold">Date</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">기관명</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">품목명</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">수량</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">상태</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">운임</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">특이 사항</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">담당 CS 팀</th>
                        @if($this->isCsTeamMenu)
                            <th class="px-3 py-2 text-center text-xs font-semibold">작업</th>
                        @endif
                        @if($this->canDeleteReturnGroups)
                            <th class="px-3 py-2 text-center text-xs font-semibold">삭제</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($registrationGroups as $group)
                        <tr wire:key="return-group-{{ $group['key'] }}">
                            <td class="px-3 py-2 text-center whitespace-nowrap text-gray-700">
                                {{ $group['returned_at'] }}
                            </td>
                            <td class="px-3 py-2 text-center text-gray-900">
                                <button type="button"
                                        wire:click="openDetailModal({{ $group['anchor_id'] }})"
                                        class="w-full rounded-sm text-center text-blue-700 hover:text-blue-900 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                                    <div class="break-words text-center font-medium leading-snug">{{ $group['institution_name'] }}</div>
                                    @if(filled($group['institution_sk_code']))
                                        <div class="mt-0.5 break-all text-center text-xs text-gray-500">{{ $group['institution_sk_code'] }}</div>
                                    @endif
                                    @if(filled($group['ecount_slip_no'] ?? null))
                                        <div class="mt-0.5 break-all text-center text-xs text-gray-500">전표 {{ $group['ecount_slip_no'] }}</div>
                                    @endif
                                </button>
                            </td>
                            <td class="px-3 py-2 text-center text-gray-800">{{ $group['item_summary'] }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ number_format($group['total_quantity']) }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $group['status_summary'] }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $group['freight'] ?: '-' }}</td>
                            <td class="px-3 py-2 text-left text-gray-600">
                                <span class="line-clamp-2 break-words text-xs leading-snug" title="{{ $group['notes_summary'] }}">{{ $group['notes_summary'] ?: '-' }}</span>
                            </td>
                            <td class="px-3 py-2 text-center text-gray-700">
                                <span class="truncate" title="{{ $group['cs_team'] }}">{{ $group['cs_team'] ?: '-' }}</span>
                            </td>
                            @if($this->isCsTeamMenu)
                                <td class="px-3 py-2 text-center">
                                    @if($group['is_completed'])
                                        <span class="text-xs font-medium text-gray-500">완료</span>
                                    @else
                                        <button type="button"
                                                wire:click="completeReturnGroup({{ $group['anchor_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="completeReturnGroup({{ $group['anchor_id'] }})"
                                                class="rounded-lg border border-green-600 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50 disabled:cursor-not-allowed disabled:opacity-60">
                                            완료
                                        </button>
                                    @endif
                                </td>
                            @endif
                            @if($this->canDeleteReturnGroups)
                                <td class="px-3 py-2 text-center">
                                    <button type="button"
                                            wire:click="deleteReturnGroup({{ $group['anchor_id'] }})"
                                            wire:confirm="이 반품 등록 건을 삭제할까요? 되돌릴 수 없습니다."
                                            wire:loading.attr="disabled"
                                            wire:target="deleteReturnGroup({{ $group['anchor_id'] }})"
                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60">
                                        삭제
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->listTableColumnCount }}" class="px-3 py-8 text-center text-sm text-gray-500">
                                등록된 반품 내역이 없습니다.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($registrationGroups->hasPages())
            <div class="border-t border-gray-100 px-3 py-3">
                {{ $registrationGroups->links() }}
            </div>
        @endif
    </div>

    @if($showDetailModal)
        <div class="mochi-modal-overlay" wire:key="store-return-detail-modal" wire:click.self="closeDetailModal">
            <div class="mochi-modal-shell flex max-h-[min(90vh,calc(100dvh-2rem))] w-full max-w-6xl flex-col overflow-hidden" wire:click.stop>
                <x-admin.modal-header title="반품 상세" close-action="closeDetailModal" />

                @if($detailEditMode)
                <form wire:submit.prevent="saveDetail" class="mochi-modal-body-scroll space-y-4 px-6 py-5">
                    <div class="overflow-x-auto">
                        <div class="grid min-w-[720px] grid-cols-[7.5rem_minmax(0,1fr)_5.5rem_6.5rem] gap-4">
                            <div class="flex flex-col gap-1">
                                <label for="detail-return-date" class="text-xs font-medium text-gray-600">Date</label>
                                <input id="detail-return-date"
                                       type="date"
                                       wire:model="detailReturnDate"
                                       class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                @error('detailReturnDate') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="relative flex min-w-0 flex-col gap-1">
                                <label for="detail-institution-keyword" class="text-xs font-medium text-gray-600">기관명</label>
                                <input id="detail-institution-keyword"
                                       type="text"
                                       wire:model.live.debounce.200ms="detailInstitutionKeyword"
                                       placeholder="기관명 직접 입력 또는 SK 코드 검색"
                                       class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">

                                @if(filled($detailInstitutionKeyword) && blank($detailInstitutionSkCode) && $this->detailInstitutionSuggestions->isNotEmpty())
                                    <div class="absolute top-full z-20 mt-1 max-h-44 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                                        @foreach($this->detailInstitutionSuggestions as $institution)
                                            <button type="button"
                                                    wire:click="selectDetailInstitution('{{ $institution->SKcode }}')"
                                                    class="w-full px-3 py-2 text-left text-sm transition-colors hover:bg-blue-50">
                                                <span class="font-medium text-gray-900">{{ $institution->resolvedAccountName() }}</span>
                                                <span class="ml-2 text-xs text-gray-500">({{ $institution->SKcode }})</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif

                                @if(filled($detailInstitutionSkCode))
                                    <p class="text-xs text-gray-500">{{ $detailInstitutionSkCode }}</p>
                                @endif

                                @error('detailInstitutionKeyword') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="detail-freight" class="text-xs font-medium text-gray-600">운임</label>
                                <select id="detail-freight"
                                        wire:model="detailFreight"
                                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                    <option value="">선택 안 함</option>
                                    @foreach($freightOptions as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('detailFreight') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="detail-cs-team" class="text-xs font-medium text-gray-600">담당 CS 팀</label>
                                <input id="detail-cs-team"
                                       type="text"
                                       wire:model="detailCsTeam"
                                       placeholder="담당 CS"
                                       class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                @error('detailCsTeam') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mt-4 flex flex-col gap-1">
                            <label for="detail-shipping-address" class="text-xs font-medium text-gray-600">배송지</label>
                            <input id="detail-shipping-address"
                                   type="text"
                                   wire:model="detailShippingAddress"
                                   placeholder="Ecount 주문서 배송지"
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            @error('detailShippingAddress') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @error('detailItemRows') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full min-w-[960px] text-sm">
                            <thead class="bg-gray-50 text-gray-700">
                                <tr>
                                    <th class="px-3 py-2 text-center text-xs font-semibold">품목명</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold">수량</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold">상태</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold">특이 사항</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold">Class Name</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold">Ecount 적요</th>
                                    @if(count($detailItemRows) > 1)
                                        <th class="w-12 px-2 py-2 text-center text-xs font-semibold"><span class="sr-only">삭제</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($detailItemRows as $index => $row)
                                    <tr wire:key="return-detail-item-{{ $row['id'] ?? 'new-'.$index }}">
                                        <td class="px-3 py-2">
                                            @include('partials.store.return-item-name-field', [
                                                'wireModel' => 'detailItemRows.'.$index.'.itemName',
                                                'id' => 'detail-item-name-'.$index,
                                                'errorKey' => 'detailItemRows.'.$index.'.itemName',
                                                'ecountProductOptions' => $ecountProductOptions,
                                                'currentValue' => $row['itemName'],
                                                'inputClass' => 'w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header',
                                            ])
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number"
                                                   min="1"
                                                   wire:model="detailItemRows.{{ $index }}.quantity"
                                                   class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                            @error('detailItemRows.'.$index.'.quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <select wire:model="detailItemRows.{{ $index }}.status"
                                                    class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                                @foreach($statusOptions as $option)
                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                @endforeach
                                            </select>
                                            @error('detailItemRows.'.$index.'.status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text"
                                                   wire:model="detailItemRows.{{ $index }}.notes"
                                                   placeholder="특이 사항"
                                                   class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                            @error('detailItemRows.'.$index.'.notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text"
                                                   wire:model="detailItemRows.{{ $index }}.className"
                                                   placeholder="Class Name"
                                                   class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                            @error('detailItemRows.'.$index.'.className') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text"
                                                   wire:model="detailItemRows.{{ $index }}.ecountRemarks"
                                                   placeholder="Ecount 적요"
                                                   class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                            @error('detailItemRows.'.$index.'.ecountRemarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </td>
                                        @if(count($detailItemRows) > 1)
                                            <td class="px-2 py-2 text-center">
                                                <button type="button"
                                                        wire:click="removeDetailItemRow({{ $index }})"
                                                        class="rounded-lg border border-gray-300 p-1.5 text-gray-500 hover:bg-gray-50 hover:text-red-600"
                                                        title="행 삭제"
                                                        aria-label="행 삭제">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-center">
                        <button type="button"
                                wire:click="addDetailItemRow"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-600 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            품목 추가하기
                        </button>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button"
                                wire:click="cancelDetailEdit"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            취소
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="saveDetail"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveDetail">저장</span>
                            <span wire:loading.inline wire:target="saveDetail" class="inline-flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                </svg>
                                저장 중...
                            </span>
                        </button>
                    </div>
                </form>
                @else
                <div class="mochi-modal-body-scroll space-y-4 px-6 py-5">
                    <div class="overflow-x-auto">
                        <dl class="grid min-w-[720px] grid-cols-[7.5rem_minmax(0,1fr)_5.5rem_6.5rem] gap-4 text-sm">
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500">Date</dt>
                                <dd class="mt-1 font-medium whitespace-nowrap text-gray-900">{{ $detailReturnDate }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500">기관명</dt>
                                <dd class="mt-1 font-medium text-gray-900">
                                    {{ $detailInstitutionKeyword }}
                                    @if(filled($detailInstitutionSkCode))
                                        <span class="ml-1 text-xs font-normal text-gray-500">{{ $detailInstitutionSkCode }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500">운임</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ $detailFreight ?: '-' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500">담당 CS 팀</dt>
                                <dd class="mt-1 truncate font-medium text-gray-900" title="{{ $detailCsTeam }}">{{ $detailCsTeam ?: '-' }}</dd>
                            </div>
                        </dl>
                        <dl class="mt-4 grid min-w-[720px] grid-cols-1 gap-4 text-sm">
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500">배송지</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ filled($detailShippingAddress) ? $detailShippingAddress : '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full min-w-[800px] text-sm">
                            <thead class="bg-gray-50 text-gray-700">
                                <tr>
                                    <th class="px-3 py-2 text-center text-xs font-semibold">품목명</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold">수량</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold">상태</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold">특이 사항</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold">Class Name</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold">Ecount 적요</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($detailItemRows as $row)
                                    <tr wire:key="return-detail-view-item-{{ $row['id'] ?? $loop->index }}">
                                        <td class="px-3 py-2 text-center text-gray-800">{{ $row['itemDisplayName'] ?? $row['itemName'] }}</td>
                                        <td class="px-3 py-2 text-center text-gray-700">{{ number_format((int) $row['quantity']) }}</td>
                                        <td class="px-3 py-2 text-center text-gray-700">{{ $row['status'] }}</td>
                                        <td class="px-3 py-2 text-left text-gray-600">{{ filled($row['notes']) ? $row['notes'] : '-' }}</td>
                                        <td class="px-3 py-2 text-left text-gray-600">{{ filled($row['className']) ? $row['className'] : '-' }}</td>
                                        <td class="px-3 py-2 text-left text-gray-600">{{ filled($row['ecountRemarks']) ? $row['ecountRemarks'] : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-4">
                        @if($this->isCsTeamMenu && config('store.return_registration.sale_order_enabled'))
                            @if(filled($detailEcountSlipNo))
                                <span class="text-sm text-gray-600">Ecount 전표 {{ $detailEcountSlipNo }}</span>
                            @endif
                            <button type="button"
                                    wire:click="createEcountSaleOrder({{ $detailAnchorId }})"
                                    wire:confirm="Ecount 주문서를 생성할까요?"
                                    wire:loading.attr="disabled"
                                    wire:target="createEcountSaleOrder({{ $detailAnchorId }})"
                                    @disabled(filled($detailEcountSlipNo))
                                    class="rounded-lg border border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-60">
                                Ecount 주문서 생성
                            </button>
                        @endif
                        <button type="button"
                                wire:click="closeDetailModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            닫기
                        </button>
                        @if($this->isCsTeamMenu && $this->isDetailGroupCompleted)
                            <span class="text-sm font-medium text-gray-500">완료</span>
                        @elseif($this->isCsTeamMenu)
                            <button type="button"
                                    wire:click="completeReturnGroup({{ $detailAnchorId }})"
                                    wire:loading.attr="disabled"
                                    wire:target="completeReturnGroup({{ $detailAnchorId }})"
                                    class="rounded-lg border border-green-600 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50 disabled:cursor-not-allowed disabled:opacity-60">
                                완료
                            </button>
                        @endif
                        @unless($this->isCsTeamMenu)
                        <button type="button"
                                wire:click="startDetailEdit"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            수정하기
                        </button>
                        @endunless
                    </div>
                </div>
                @endif
            </div>
        </div>
    @endif

    @if($showCreateModal)
        <div class="mochi-modal-overlay" wire:key="store-return-create-modal" wire:click.self="closeCreateModal">
            <div class="mochi-modal-shell flex max-h-[min(90vh,calc(100dvh-2rem))] w-full max-w-6xl flex-col overflow-hidden" wire:click.stop>
                <x-admin.modal-header title="반품 등록" close-action="closeCreateModal" />

                <form wire:submit.prevent="save" class="mochi-modal-body-scroll px-6 py-5">
                    <div class="mb-4 max-w-xs">
                        <label for="return-date" class="text-xs font-medium text-gray-600">Date</label>
                        <input id="return-date"
                               type="date"
                               wire:model="returnDate"
                               class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                        @error('returnDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-4">
                        @foreach($institutionBlocks as $blockIndex => $block)
                            @php
                                $blockItemCount = count($block['itemRows']);
                                $formGridClass = $blockItemCount > 1
                                    ? 'md:grid-cols-[minmax(0,1fr)_5.5rem_7rem_minmax(0,1fr)_2.25rem]'
                                    : 'md:grid-cols-[minmax(0,1fr)_5.5rem_7rem_minmax(0,1fr)]';
                            @endphp

                            <div wire:key="return-institution-block-{{ $blockIndex }}"
                                 @class([
                                     'rounded-lg border border-gray-200 p-4' => count($institutionBlocks) > 1,
                                 ])>
                                @if(count($institutionBlocks) > 1)
                                    <div class="mb-3 flex items-center justify-between gap-2">
                                        <h3 class="text-sm font-semibold text-gray-700">기관 {{ $blockIndex + 1 }}</h3>
                                        <button type="button"
                                                wire:click="removeInstitutionBlock({{ $blockIndex }})"
                                                class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-50 hover:text-red-600">
                                            기관 삭제
                                        </button>
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 gap-x-4 gap-y-2 md:grid-cols-[minmax(0,1fr)_5.5rem]">
                                    <div class="flex flex-col gap-1">
                                        <label for="institution-keyword-{{ $blockIndex }}" class="text-xs font-medium text-gray-600">기관명</label>
                                        <input id="institution-keyword-{{ $blockIndex }}"
                                               type="text"
                                               wire:model.live.debounce.200ms="institutionBlocks.{{ $blockIndex }}.institutionKeyword"
                                               placeholder="기관명 직접 입력 또는 SK 코드 검색"
                                               class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">

                                        @if(filled($block['institutionKeyword']) && blank($block['institutionSkCode']) && $this->institutionSuggestionsFor($blockIndex)->isNotEmpty())
                                            <div class="max-h-44 overflow-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                                                @foreach($this->institutionSuggestionsFor($blockIndex) as $institution)
                                                    <button type="button"
                                                            wire:click="selectInstitution({{ $blockIndex }}, '{{ $institution->SKcode }}')"
                                                            class="w-full px-3 py-2 text-left text-sm transition-colors hover:bg-blue-50">
                                                        <span class="font-medium text-gray-900">{{ $institution->resolvedAccountName() }}</span>
                                                        <span class="ml-2 text-xs text-gray-500">({{ $institution->SKcode }})</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @elseif(filled(trim($block['institutionKeyword'])) && blank($block['institutionSkCode']) && $this->institutionSuggestionsFor($blockIndex)->isEmpty())
                                            <p class="text-xs text-gray-500">검색 결과가 없습니다. 입력한 기관명으로 등록할 수 있습니다.</p>
                                        @endif

                                        @error('institutionBlocks.'.$blockIndex.'.institutionKeyword') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="flex flex-col gap-1">
                                        <label for="freight-{{ $blockIndex }}" class="text-xs font-medium text-gray-600">운임</label>
                                        <select id="freight-{{ $blockIndex }}"
                                                wire:model="institutionBlocks.{{ $blockIndex }}.freight"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                            <option value="">선택 안 함</option>
                                            @foreach($freightOptions as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        @error('institutionBlocks.'.$blockIndex.'.freight') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="mt-3 hidden gap-x-4 gap-y-2 text-xs font-medium text-gray-600 md:grid {{ $formGridClass }}">
                                    <span>품목명</span>
                                    <span>수량</span>
                                    <span>상태</span>
                                    <span>특이 사항</span>
                                    @if($blockItemCount > 1)
                                        <span class="sr-only">삭제</span>
                                    @endif
                                </div>

                                @foreach($block['itemRows'] as $index => $row)
                                    <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-2 {{ $formGridClass }} md:items-start"
                                         wire:key="return-item-row-{{ $blockIndex }}-{{ $index }}">
                                        <div class="flex flex-col gap-1">
                                            @if($loop->first)
                                                <label class="text-xs font-medium text-gray-600 md:sr-only" for="item-name-{{ $blockIndex }}-{{ $index }}">품목명</label>
                                            @endif
                                            @include('partials.store.return-item-name-field', [
                                                'wireModel' => 'institutionBlocks.'.$blockIndex.'.itemRows.'.$index.'.itemName',
                                                'id' => 'item-name-'.$blockIndex.'-'.$index,
                                                'errorKey' => 'institutionBlocks.'.$blockIndex.'.itemRows.'.$index.'.itemName',
                                                'ecountProductOptions' => $ecountProductOptions,
                                                'currentValue' => $row['itemName'],
                                            ])
                                        </div>

                                        <div class="flex flex-col gap-1">
                                            @if($loop->first)
                                                <label class="text-xs font-medium text-gray-600 md:sr-only" for="quantity-{{ $blockIndex }}-{{ $index }}">수량</label>
                                            @endif
                                            <input id="quantity-{{ $blockIndex }}-{{ $index }}"
                                                   type="number"
                                                   min="1"
                                                   wire:model="institutionBlocks.{{ $blockIndex }}.itemRows.{{ $index }}.quantity"
                                                   placeholder="수량"
                                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                            @error('institutionBlocks.'.$blockIndex.'.itemRows.'.$index.'.quantity') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>

                                        <div class="flex flex-col gap-1">
                                            @if($loop->first)
                                                <label class="text-xs font-medium text-gray-600 md:sr-only" for="status-{{ $blockIndex }}-{{ $index }}">상태</label>
                                            @endif
                                            <select id="status-{{ $blockIndex }}-{{ $index }}"
                                                    wire:model="institutionBlocks.{{ $blockIndex }}.itemRows.{{ $index }}.status"
                                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                                @foreach($statusOptions as $option)
                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                @endforeach
                                            </select>
                                            @error('institutionBlocks.'.$blockIndex.'.itemRows.'.$index.'.status') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>

                                        <div class="flex flex-col gap-1">
                                            @if($loop->first)
                                                <label class="text-xs font-medium text-gray-600 md:sr-only" for="notes-{{ $blockIndex }}-{{ $index }}">특이 사항</label>
                                            @endif
                                            <input id="notes-{{ $blockIndex }}-{{ $index }}"
                                                   type="text"
                                                   wire:model="institutionBlocks.{{ $blockIndex }}.itemRows.{{ $index }}.notes"
                                                   placeholder="특이 사항"
                                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                            @error('institutionBlocks.'.$blockIndex.'.itemRows.'.$index.'.notes') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>

                                        @if($blockItemCount > 1)
                                            <div class="flex items-start justify-end md:justify-center md:pt-1">
                                                <button type="button"
                                                        wire:click="removeItemRow({{ $blockIndex }}, {{ $index }})"
                                                        class="rounded-lg border border-gray-300 p-2 text-gray-500 hover:bg-gray-50 hover:text-red-600"
                                                        title="행 삭제"
                                                        aria-label="행 삭제">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach

                                @error('institutionBlocks.'.$blockIndex.'.itemRows') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <button type="button"
                                wire:click="addItemRow"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-600 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            행 추가하기
                        </button>
                        <button type="button"
                                wire:click="addInstitutionBlock"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-600 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            기관 추가하기
                        </button>
                    </div>

                    <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button"
                                wire:click="closeCreateModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            취소
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="save"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">등록</span>
                            <span wire:loading.inline wire:target="save" class="inline-flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                </svg>
                                저장 중...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
