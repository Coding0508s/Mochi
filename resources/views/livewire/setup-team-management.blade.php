<div class="mochi-page">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <h3 class="text-sm font-semibold text-gray-800">조직/팀 관리</h3>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <div class="relative min-w-64">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="팀코드/팀명 검색"
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="button"
                        wire:click="openCreateModal"
                        class="py-2 px-3 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 cursor-pointer">
                    팀 생성
                </button>
            </div>
        </div>
    </div>

    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="px-3 py-2 text-left text-xs font-semibold">팀코드</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">팀명</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">상위부서</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">위치</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">소속 인원</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">액션</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($teams as $team)
                        <tr wire:key="setup-team-{{ $team->DEPTNO }}" class="mochi-table-row-hover transition-colors">
                            <td class="px-3 py-2 font-medium text-gray-900">{{ $team->DEPTNO }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $team->DEPTNAME ?: '-' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $team->ADMRDEPT ?: '-' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $team->LOCATION ?: '-' }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ (int) ($team->employee_count ?? 0) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button"
                                            wire:click="openEditModal('{{ $team->DEPTNO }}')"
                                            class="px-2 py-1 text-xs rounded border border-blue-200 text-blue-700 hover:bg-blue-50 cursor-pointer">
                                        수정
                                    </button>
                                    <button type="button"
                                            wire:click="openDeleteModal('{{ $team->DEPTNO }}')"
                                            class="px-2 py-1 text-xs rounded border border-rose-200 text-rose-700 hover:bg-rose-50 cursor-pointer">
                                        삭제
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center text-gray-400">
                                <p class="font-medium">팀 데이터가 없습니다</p>
                                <p class="text-sm mt-1">검색 조건을 변경하거나 팀을 생성해 주세요.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($teams->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $teams->links() }}
            </div>
        @endif
    </div>

    @if($showCreateModal)
        <div class="mochi-modal-overlay" wire:key="setup-team-create-modal">
            <div class="mochi-modal-shell max-w-xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">팀 생성</h3>
                    <button type="button" wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="createTeam" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">팀명</label>
                        <input type="text" wire:model.defer="newDeptName"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('newDeptName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">상위부서</label>
                        <select wire:model.defer="newAdmrDept"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">미지정</option>
                            @foreach($parentOptions as $dept)
                                <option value="{{ $dept->DEPTNO }}">{{ $dept->DEPTNAME ?: $dept->DEPTNO }}</option>
                            @endforeach
                        </select>
                        @error('newAdmrDept') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">위치</label>
                        <input type="text" wire:model.defer="newLocation"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('newLocation') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-indigo-600">팀코드(DEPTNO)는 A01 형식으로 자동 생성됩니다.</p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeCreateModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                                wire:loading.attr="disabled"
                                wire:target="createTeam">
                            생성
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showEditModal)
        <div class="mochi-modal-overlay" wire:key="setup-team-edit-modal">
            <div class="mochi-modal-shell max-w-xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">팀 수정</h3>
                    <button type="button" wire:click="closeEditModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="updateTeam" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">팀코드</label>
                        <input type="text" value="{{ $editDeptNo }}" disabled
                               class="w-full px-3 py-2 text-sm border border-gray-200 bg-gray-100 text-gray-500 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">팀명</label>
                        <input type="text" wire:model.defer="editDeptName"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @error('editDeptName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">상위부서</label>
                        <select wire:model.defer="editAdmrDept"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">미지정</option>
                            @foreach($parentOptions as $dept)
                                <option value="{{ $dept->DEPTNO }}">{{ $dept->DEPTNAME ?: $dept->DEPTNO }}</option>
                            @endforeach
                        </select>
                        @error('editAdmrDept') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">위치</label>
                        <input type="text" wire:model.defer="editLocation"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @error('editLocation') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeEditModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                wire:loading.attr="disabled"
                                wire:target="updateTeam">
                            저장
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div class="mochi-modal-overlay" wire:key="setup-team-delete-modal">
            <div class="mochi-modal-shell max-w-md">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">팀 삭제 확인</h3>
                </div>

                <div class="px-6 py-5">
                    <p class="text-sm text-gray-700">
                        팀 <span class="font-semibold text-rose-700">{{ $deleteDeptNo }}</span> 을(를) 삭제하시겠습니까?
                    </p>
                    <p class="mt-2 text-xs text-rose-600">
                        소속 직원이 있으면 삭제되지 않습니다.
                    </p>
                    @error('deleteDeptNo') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" wire:click="closeDeleteModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">
                            취소
                        </button>
                        <button type="button" wire:click="deleteTeam"
                                class="px-4 py-2 text-sm bg-rose-600 text-white rounded-lg hover:bg-rose-700"
                                wire:loading.attr="disabled"
                                wire:target="deleteTeam">
                            삭제
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

