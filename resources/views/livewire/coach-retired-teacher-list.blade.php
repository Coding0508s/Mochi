<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm" data-mochi-flash-dismiss="3000" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">퇴직교사 리스트</h2>
            <span class="text-gray-300">|</span>
            <span class="text-gray-500">
                @if($tableMissing ?? false)
                    퇴직교사 마스터 테이블을 사용할 수 없습니다.
                @else
                    @if(filled($filterYear))
                        {{ $filterYear }}년 ·
                    @else
                        전체 ·
                    @endif
                    <span class="font-semibold text-gray-700">{{ $retirements->total() }}</span>명
                @endif
            </span>
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
                        class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">전체</option>
                    @for($y = now()->year; $y >= now()->year - 10; $y--)
                        <option value="{{ $y }}">{{ $y }}년</option>
                    @endfor
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
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                @if($search)
                    <button wire:click="resetFilters"
                            class="py-2 px-3 text-sm text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                        초기화
                    </button>
                @endif
            </div>
        </div>

        <div class="mochi-table-card overflow-x-auto isolate">
            <table class="w-full min-w-[860px] text-sm border-collapse">
                <thead>
                <tr class="bg-[#f5f0e8] text-gray-700">
                    <th class="px-3 py-2 text-left border border-gray-300">SK</th>
                    <th class="px-3 py-2 text-left border border-gray-300">기관명</th>
                    <th class="px-3 py-2 text-left border border-gray-300">교사명</th>
                    <th class="px-3 py-2 text-left border border-gray-300">직급</th>
                    <th class="px-3 py-2 text-left border border-gray-300">퇴직일</th>
                    <th class="px-3 py-2 text-left border border-gray-300">TR</th>
                    <th class="px-3 py-2 text-center border border-gray-300">추천</th>
                </tr>
                </thead>
                <tbody>
                @forelse($retirements as $row)
                    <tr class="hover:bg-blue-50/40 border-b border-gray-200">
                        <td class="px-3 py-2 border border-gray-200">{{ $row->SK_Code }}</td>
                        <td class="px-3 py-2 border border-gray-200">{{ $row->displayAccountName() ?: '-' }}</td>
                        <td class="px-3 py-2 border border-gray-200">
                            <button type="button"
                                    wire:click="openDetailModal({{ $row->ID }})"
                                    class="text-blue-700 underline text-left hover:text-blue-900 cursor-pointer">
                                {{ $row->Name }}
                            </button>
                        </td>
                        <td class="px-3 py-2 border border-gray-200">
                            @if($position = $row->displayPosition())
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                    {{ $position === '원장' ? 'bg-yellow-100 text-yellow-800' :
                                       ($position === '교수부장' ? 'bg-purple-100 text-purple-700' :
                                       ($position === '부원장' ? 'bg-indigo-100 text-indigo-800' :
                                       'bg-gray-100 text-gray-800')) }}">
                                    {{ $position }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 border border-gray-200 whitespace-nowrap">
                            {{ $row->RetirementDate?->format('Y-m-d') ?? '-' }}
                        </td>
                        <td class="px-3 py-2 border border-gray-200">{{ $row->TR_Name ?: '-' }}</td>
                        <td class="px-3 py-2 border border-gray-200 text-center">
                            @if($row->displayRecommendYn())
                                <span class="text-green-700 font-medium">Y</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                            조건에 맞는 퇴직 교사가 없습니다.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            @if($retirements->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $retirements->links() }}
                </div>
            @endif
        </div>
    @endif

    @if($showDetailModal && $selectedRetirement)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
             wire:click.self="closeDetailModal">
            <div class="mochi-modal bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto"
                 role="dialog" aria-modal="true">
                <x-admin.modal-header title="퇴직 교사 상세" close-action="closeDetailModal" />
                <div class="px-5 py-4 space-y-3 text-sm">
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
                <div class="px-5 py-4 border-t border-gray-200 flex justify-end gap-2">
                    @if($selectedRetirement['can_reinstate'] ?? false)
                        <button type="button" wire:click="openReinstateModal"
                                class="px-4 py-2 text-sm text-emerald-700 border border-emerald-200 rounded-lg hover:bg-emerald-50 cursor-pointer">
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
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/40"
             wire:click.self="closeReinstateModal">
            <div class="mochi-modal bg-white rounded-lg shadow-xl w-full max-w-md"
                 wire:click.stop>
                <x-admin.modal-header title="복직 처리" close-action="closeReinstateModal" />
                <div class="px-5 py-4 space-y-4 text-sm text-gray-700">
                    <p>
                        <span class="font-semibold text-gray-900">{{ $reinstateTargetName }}</span> 교사를 복직 처리합니다.
                        퇴직교사 리스트에서는 제외되며, 교사 지원·연락처 목록에 다시 표시됩니다.
                    </p>
                    @include('partials.admin.teacher-reinstate-fields')
                </div>
                <div class="px-5 py-4 border-t border-gray-200 flex justify-end gap-2">
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
