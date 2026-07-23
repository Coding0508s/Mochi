<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2" data-mochi-flash-dismiss="3000" role="status">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
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

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-mochi-header">퇴직교사 리스트</h2>
            <span class="text-gray-300">|</span>
            @if($tableMissing ?? false)
                <span class="text-gray-500">퇴직교사 마스터 테이블을 사용할 수 없습니다.</span>
            @else
                <span class="text-gray-500">
                    @if(filled($filterYear))
                        {{ $filterYear }}년
                    @else
                        전체
                    @endif
                </span>
                <span class="ml-auto text-gray-500">현재 조건 결과: <span class="font-semibold text-gray-700">{{ $retirements->total() }}</span>명</span>
            @endif
        </div>
    </div>

    @if($tableMissing ?? false)
        <div class="mochi-table-card p-8 text-center text-gray-500 text-sm">
            <p>S_TeacherMasterDB 테이블이 없어 목록을 표시할 수 없습니다.</p>
        </div>
    @else
        <div class="mochi-filter-card">
            <div class="flex flex-wrap items-center gap-3">
                <select wire:model.live="filterYear"
                        class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    <option value="">전체</option>
                    @for($y = now()->year; $y >= now()->year - 10; $y--)
                        <option value="{{ $y }}">{{ $y }}년</option>
                    @endfor
                </select>

                <select wire:model.live="filterStatus"
                        aria-label="상태 필터"
                        class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    <option value="">전체 상태</option>
                    <option value="retired">퇴직</option>
                    <option value="reinstated">복직</option>
                </select>

                <select wire:model.live="filterRecommend"
                        aria-label="추천 필터"
                        class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                    <option value="">전체 추천</option>
                    <option value="yes">추천 (Y)</option>
                    <option value="no">비추천 (-)</option>
                </select>

                <div class="relative flex-1 min-w-56">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="이름, 기관명, SK코드 검색"
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                </div>

                @if($search || $filterYear !== '' || $filterStatus !== '' || $filterRecommend !== '')
                    <button wire:click="resetFilters"
                            type="button"
                            class="py-2 px-3 text-sm text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                        초기화
                    </button>
                @endif

                <div class="ml-auto flex flex-wrap items-center gap-3 max-md:ml-0 max-md:w-full">
                    <button type="button"
                            wire:click="exportToExcel"
                            wire:loading.attr="disabled"
                            wire:target="exportToExcel"
                            class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60 max-md:w-full">
                        <span wire:loading.remove wire:target="exportToExcel" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            엑셀 다운로드
                        </span>
                        <span wire:loading.inline-flex wire:target="exportToExcel" class="hidden items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            생성 중…
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <div class="mochi-table-card">
            <div class="overflow-x-auto isolate">
                <table class="w-full min-w-[980px] text-sm whitespace-nowrap">
                    <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="px-3 py-2 text-left text-xs font-semibold">SK</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">기관명</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">교사명</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">교사 전화번호</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">직급</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">퇴직일</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">TR</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">상태</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">추천</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($retirements as $row)
                        <tr wire:key="retired-row-{{ $row->ID }}"
                            wire:click="openDetailModal({{ $row->ID }})"
                            class="mochi-table-row-hover transition-colors cursor-pointer">
                            <td class="px-3 py-2.5">
                                @if($row->SK_Code)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                        {{ $row->SK_Code }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-gray-700 max-w-36 truncate" title="{{ $row->displayAccountName() }}">
                                {{ $row->displayAccountName() ?: '-' }}
                            </td>
                            <td class="px-3 py-2.5 font-medium text-gray-900">{{ $row->Name }}</td>
                            <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">
                                {{ $row->displayPhone() ?? '-' }}
                            </td>
                            <td class="px-3 py-2.5">
                                @if($position = $row->displayPosition())
                                    <span class="text-xs text-gray-600">{{ $position }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">
                                {{ $row->RetirementDate?->format('Y-m-d') ?? '-' }}
                            </td>
                            <td class="px-3 py-2.5 text-gray-600 text-xs">{{ $row->TR_Name ?: '-' }}</td>
                            <td class="px-3 py-2.5 text-center">
                                @php($isRetiredRow = trim((string) $row->Status) === '퇴직')
                                @if($isRetiredRow)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">퇴직</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">복직</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if($row->displayRecommendYn())
                                    <span class="text-green-700 font-medium">Y</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-16 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <p class="font-medium">검색 결과가 없습니다</p>
                                <p class="text-sm mt-1">검색어 또는 필터 조건을 변경해 보세요.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($retirements->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $retirements->links() }}
                </div>
            @endif
        </div>
    @endif

    @if($showDetailModal && $selectedRetirement)
        <div class="mochi-modal-overlay"
             wire:click.self="closeDetailModal">
            <div class="mochi-modal-shell max-w-lg max-h-[90vh] flex flex-col overflow-hidden"
                 wire:click.stop
                 role="dialog" aria-modal="true">
                <x-admin.modal-header title="퇴직 교사 상세" close-action="closeDetailModal" />
                <div class="px-6 py-5 space-y-3 text-sm overflow-y-auto">
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-500">교사명</span>
                        <span class="col-span-2 text-gray-900">{{ $selectedRetirement['name'] ?? '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-500">직급</span>
                        <span class="col-span-2 text-gray-900">{{ $selectedRetirement['position'] ?? '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-500">기관명</span>
                        <span class="col-span-2 text-gray-900">{{ $selectedRetirement['account_name'] ?? '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-500">SK 코드</span>
                        <span class="col-span-2 text-gray-900">{{ $selectedRetirement['sk_code'] ?? '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-500">퇴직일</span>
                        <span class="col-span-2 text-gray-900">{{ $selectedRetirement['retirement_date'] ?? '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-500">TR</span>
                        <span class="col-span-2 text-gray-900">{{ $selectedRetirement['tr_name'] ?? '-' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-500">상태</span>
                        <span class="col-span-2">
                            @php($isRetiredDetail = trim((string) ($selectedRetirement['status'] ?? '')) === '퇴직')
                            @if($isRetiredDetail)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">퇴직</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">복직</span>
                            @endif
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-500">추천</span>
                        <span class="col-span-2 text-gray-900">
                            {{ ($selectedRetirement['recommend_yn'] ?? false) ? '예' : '아니오' }}
                        </span>
                    </div>
                    @if(filled($selectedRetirement['recommend_description'] ?? null))
                        <div class="grid grid-cols-3 gap-2">
                            <span class="text-gray-500">추천 사유</span>
                            <span class="col-span-2 text-gray-900">{{ $selectedRetirement['recommend_description'] }}</span>
                        </div>
                    @endif
                    @if(filled($selectedRetirement['description'] ?? null))
                        <div class="grid grid-cols-3 gap-2">
                            <span class="text-gray-500">비고</span>
                            <span class="col-span-2 text-gray-900">{{ $selectedRetirement['description'] }}</span>
                        </div>
                    @endif
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    @if($selectedRetirement['can_reinstate'] ?? false)
                        <button type="button" wire:click="openReinstateModal"
                                class="px-4 py-2 text-sm text-emerald-700 border border-emerald-300 rounded-lg hover:bg-emerald-50 cursor-pointer">
                            복직 처리
                        </button>
                    @endif
                    <button type="button" wire:click="closeDetailModal"
                            class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg cursor-pointer">
                        닫기
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showReinstateModal)
        <div class="mochi-modal-overlay z-[60]"
             wire:click.self="closeReinstateModal">
            <div class="mochi-modal-shell max-w-md"
                 wire:click.stop>
                <x-admin.modal-header title="복직 처리" close-action="closeReinstateModal" />
                <div class="px-6 py-5 space-y-4 text-sm text-gray-700">
                    <p>
                        <span class="font-semibold text-gray-900">{{ $reinstateTargetName }}</span> 교사를 복직 처리합니다.
                        교사 지원·연락처 목록에 다시 표시되며, 퇴직 이력은 이 리스트에 "복직" 상태로 남습니다.
                    </p>
                    @include('partials.admin.teacher-reinstate-fields')
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <button type="button" wire:click="closeReinstateModal"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 cursor-pointer">
                        취소
                    </button>
                    <button type="button" wire:click="reinstate"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg cursor-pointer">
                        <span wire:loading.remove wire:target="reinstate">복직 확인</span>
                        <span wire:loading wire:target="reinstate">처리 중...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
