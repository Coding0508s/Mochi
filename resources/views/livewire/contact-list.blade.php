<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- 요약 영역 --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">기관 연락처</h2>
            <span class="text-gray-300">|</span>
            <button wire:click="$set('employmentFilter', 'all')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $employmentFilter === 'all' ? 'font-semibold text-blue-700' : '' }}">
                전체 <span class="font-semibold text-blue-600">{{ $totalCount }}</span>
            </button>
            <button wire:click="$set('employmentFilter', 'active')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $employmentFilter === 'active' ? 'font-semibold text-green-700' : '' }}">
                재직 <span class="font-semibold text-green-600">{{ $activeCount }}</span>
            </button>
            <button wire:click="$set('employmentFilter', 'inactive')"
                    class="text-gray-600 hover:text-blue-700 transition-colors cursor-pointer
                           {{ $employmentFilter === 'inactive' ? 'font-semibold text-red-700' : '' }}">
                퇴직 <span class="font-semibold text-red-500">{{ $inactiveCount }}</span>
            </button>
            <span class="ml-auto text-gray-500">현재 조건 결과: <span class="font-semibold text-gray-700">{{ $teachers->total() }}</span>명</span>
        </div>
    </div>

    {{-- 필터/검색 영역 --}}
    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-4 text-sm">
                @foreach(['name' => '이름', 'email' => '이메일', 'school' => '기관', 'phone' => '전화번호'] as $value => $label)
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" wire:model.live="searchType" value="{{ $value }}"
                               class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"/>
                        <span class="{{ $searchType === $value ? 'text-blue-600 font-semibold' : 'text-gray-600' }}">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="relative flex-1 min-w-56">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="{{ ['name' => '이름', 'email' => '이메일', 'school' => '기관명', 'phone' => '전화번호'][$searchType] }}(으)로 검색..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            @if($search)
                <button wire:click="$set('search', '')"
                        class="py-2 px-3 text-sm text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                    초기화
                </button>
            @endif

            <button wire:click="openCreateModal"
                    class="ml-auto flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                신규 생성
            </button>
        </div>
    </div>

    {{-- 목록 테이블 --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                <tr class="text-gray-700">
                    <th class="px-3 py-2 text-left text-xs font-semibold">No</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">이름</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">이메일</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">전화번호</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">기관코드</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">기관명</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">직급</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold">상태</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">GrapeSEED Essentials</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">LittleSEED Essentials</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">담당 TR</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">CS</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">CO</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">주소</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">비고</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold">액션</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($teachers as $index => $teacher)
                    <tr wire:key="contact-row-{{ $teacher->ID }}"
                        wire:click="openDetailModal({{ $teacher->ID }})"
                        class="mochi-table-row-hover transition-colors cursor-pointer">
                        <td class="px-3 py-2.5 text-gray-400 text-xs">{{ $teachers->firstItem() + $index }}</td>
                        <td class="px-3 py-2.5 font-medium text-gray-900">{{ $teacher->Name ?? '-' }}</td>
                        <td class="px-3 py-2.5 text-gray-600 text-xs">{{ $teacher->Email ?? '-' }}</td>
                        <td class="px-3 py-2.5 text-gray-600">{{ $teacher->Phone ?? '-' }}</td>
                        <td class="px-3 py-2.5">
                            @if($teacher->SK_Code)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                    {{ $teacher->SK_Code }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-gray-700 max-w-36 truncate" title="{{ $teacher->School_Name }}">{{ $teacher->School_Name ?? '-' }}</td>
                        <td class="px-3 py-2.5">
                            @if($teacher->Position)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                    {{ $teacher->Position }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if(in_array(trim((string) $teacher->Status), ['inactive', '비활성', '비활성화', '퇴직'], true))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">비활성화</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">활성화</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-gray-600 text-xs">
                            {{ optional($teacher->GrapeSEEDEssentials)->format('Y-m-d') ?? '-' }}
                        </td>
                        <td class="px-3 py-2.5 text-gray-600 text-xs">
                            {{ optional($teacher->LittleSEEDEssentials)->format('Y-m-d') ?? '-' }}
                        </td>
                        <td class="px-3 py-2.5 text-gray-600 text-xs">{{ $teacher->institution?->accountInfo?->TR ?? '-' }}</td>
                        <td class="px-3 py-2.5 text-gray-600 text-xs">{{ $teacher->CS ?: $teacher->institution?->accountInfo?->CS ?: '-' }}</td>
                        <td class="px-3 py-2.5 text-gray-600 text-xs">{{ $teacher->CO ?: $teacher->institution?->accountInfo?->CO ?: '-' }}</td>
                        <td class="px-3 py-2.5 text-gray-600 text-xs max-w-48 truncate" title="{{ $teacher->institution?->Address }}">
                            {{ $teacher->institution?->Address ?? '-' }}
                        </td>
                        <td class="px-3 py-2.5 text-gray-500 text-xs max-w-32 truncate" title="{{ $teacher->Description }}">
                            {{ $teacher->Description ?? '-' }}
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <div class="inline-flex items-center gap-1">
                                <button wire:click.stop="openEditModal({{ $teacher->ID }})"
                                        class="px-2 py-1 text-xs text-blue-600 border border-blue-200 rounded hover:bg-blue-50 transition-colors cursor-pointer">
                                    수정
                                </button>
                                <button wire:click.stop="confirmDelete({{ $teacher->ID }})"
                                        class="px-2 py-1 text-xs text-red-600 border border-red-200 rounded hover:bg-red-50 transition-colors cursor-pointer">
                                    삭제
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="px-4 py-16 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="font-medium">검색 결과가 없습니다</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($teachers->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $teachers->links() }}
            </div>
        @endif
    </div>

    {{-- 생성/수정 공용 모달 --}}
    @if($showModal)
        <div class="mochi-modal-overlay"
             wire:click.self="closeModal">
            <div class="mochi-modal-shell max-w-2xl"
                 wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50/80 to-white">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ $editingId ? '교사정보 수정하기' : '신규 교사 생성' }}
                    </h2>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit="save" class="max-h-[80vh] overflow-y-auto">
                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                기관명 <span class="text-red-500">*</span>
                            </label>
                            <select wire:model.live="newSkCode"
                                    class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('newSkCode') ? 'border-red-400' : 'border-gray-300' }}">
                                <option value="">기관을 선택하세요</option>
                                @foreach($institutions as $inst)
                                    <option value="{{ $inst->SKcode }}">[{{ $inst->SKcode }}] {{ $inst->AccountName }}</option>
                                @endforeach
                            </select>
                            @error('newSkCode') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            @if($newSchoolName)
                                <p class="mt-1 text-xs text-gray-500">선택 기관: {{ $newSchoolName }}</p>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="newName" placeholder="홍길동"
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('newName') ? 'border-red-400' : 'border-gray-300' }}"/>
                                @error('newName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">직급</label>
                                <select wire:model="newPosition"
                                        class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">선택</option>
                                    <option value="원장">원장</option>
                                    <option value="부원장">부원장</option>
                                    <option value="교사">교사</option>
                                    <option value="행정">행정</option>
                                    <option value="기타">기타</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">eMail <span class="text-red-500">*</span></label>
                                <input type="email" wire:model="newEmail" placeholder="example@email.com"
                                       class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('newEmail') ? 'border-red-400' : 'border-gray-300' }}"/>
                                @error('newEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" wire:model="newPhone" placeholder="010-0000-0000"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            </div>
                        </div>

                        <div class="border-t border-b border-gray-100 py-4 space-y-4">
                            <div class="grid grid-cols-[110px_1fr] items-center gap-3">
                                <label class="text-sm font-medium text-gray-700">Status</label>
                                <div class="flex items-center gap-6 text-sm">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio"
                                               wire:model="newEmploymentStatus"
                                               value="active"
                                               class="w-4 h-4 text-red-500 border-gray-300 focus:ring-red-400">
                                        <span class="text-gray-700">활성화</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio"
                                               wire:model="newEmploymentStatus"
                                               value="inactive"
                                               class="w-4 h-4 text-red-500 border-gray-300 focus:ring-red-400">
                                        <span class="text-gray-700">비활성화</span>
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-[110px_1fr] items-center gap-3">
                                <label class="text-sm font-medium text-gray-700">수업참여</label>
                                <div class="flex items-center gap-6 text-sm">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio"
                                               wire:model="newClassParticipation"
                                               value="in"
                                               class="w-4 h-4 text-red-500 border-gray-300 focus:ring-red-400">
                                        <span class="text-gray-700">수업(O)</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio"
                                               wire:model="newClassParticipation"
                                               value="out"
                                               class="w-4 h-4 text-red-500 border-gray-300 focus:ring-red-400">
                                        <span class="text-gray-700">수업(X)</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea wire:model="newDescription" rows="4"
                                      placeholder="메모할 내용을 입력하세요"
                                      class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if($editingId)
                                <button type="button"
                                        wire:click="retire"
                                        class="px-4 py-2 text-sm text-rose-600 border border-rose-200 rounded-lg hover:bg-rose-50 transition-colors cursor-pointer">
                                    퇴직처리
                                </button>
                                <button type="button"
                                        wire:click="confirmDelete({{ $editingId }})"
                                        class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                                    삭제하기
                                </button>
                            @endif
                        </div>

                        <button type="submit"
                                class="px-5 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors cursor-pointer"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-70 cursor-not-allowed">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? '수정하기' : '저장하기' }}</span>
                            <span wire:loading wire:target="save">저장 중...</span>
                        </button>
                        </div>
                </form>
            </div>
        </div>
    @endif

    {{-- 삭제 확인 모달 --}}
    @if($showDeleteModal)
        <div class="mochi-modal-overlay"
             wire:click.self="closeDeleteModal">
            <div class="mochi-modal-shell max-w-md"
                 wire:click.stop>
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">연락처 삭제 확인</h3>
                </div>
                <div class="px-6 py-5 text-sm text-gray-700">
                    <p>
                        <span class="font-semibold text-gray-900">{{ $deleteTargetName }}</span> 연락처를 삭제할까요?
                    </p>
                    <p class="text-xs text-gray-500 mt-2">삭제 후 복구할 수 없습니다.</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <button type="button" wire:click="closeDeleteModal"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 cursor-pointer">
                        취소
                    </button>
                    <button type="button" wire:click="delete"
                            class="px-4 py-2 text-sm text-white bg-red-600 hover:bg-red-700 rounded-lg cursor-pointer"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed">
                        삭제
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- 연락처 상세 모달 --}}
    @if($showDetailModal && $selectedContact)
        <div class="mochi-modal-overlay"
             wire:click.self="closeDetailModal">
            <div class="mochi-modal-shell max-w-2xl"
                 wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50/80 to-white">
                    <h2 class="text-base font-semibold text-gray-900">연락처 상세 정보</h2>
                    <button wire:click="closeDetailModal" class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5">
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">이름</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['name'] ?? '-' }}</td>
                                    <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">상태</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['status'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">이메일</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['email'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">연락처</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['phone'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">직급</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['position'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관코드</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['sk_code'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관명</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['school_name'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 TR</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['tr'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 CO</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['co'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 CS</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['cs'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">GrapeSEED Essentials</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['grape_seed_essentials'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">LittleSEED Essentials</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['little_seed_essentials'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">CO 계정명</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['co_name'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">등록일</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['created_date'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관 주소</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedContact['institution_address'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">비고</th>
                                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900 whitespace-pre-wrap">{{ $selectedContact['description'] ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div wire:loading.delay
         wire:target="save,delete,retire,openDetailModal,openEditModal,gotoPage,nextPage,previousPage"
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
