<div class="mochi-page">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <h3 class="text-sm font-semibold text-gray-800">권한/역할 관리</h3>
            <div class="ml-auto flex items-center gap-2">
                <div class="relative min-w-64">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="역할 키/역할명 검색"
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="button"
                        wire:click="openCreateModal"
                        class="py-2 px-3 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 cursor-pointer">
                    역할 생성
                </button>
            </div>
        </div>
    </div>

    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                <tr class="text-gray-700">
                    <th class="px-3 py-2 text-left text-xs font-semibold">역할 키</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">역할명</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">설명</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold">활성</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold">액션</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($roles as $role)
                    <tr wire:key="setup-role-{{ $role->id }}" class="mochi-table-row-hover transition-colors">
                        <td class="px-3 py-2 font-medium text-gray-900">{{ $role->role_key }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $role->role_name }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $role->description ?: '-' }}</td>
                        <td class="px-3 py-2 text-center">
                            @if($role->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">활성</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">비활성</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1.5">
                                <button type="button"
                                        wire:click="openEditModal({{ $role->id }})"
                                        class="px-2 py-1 text-xs rounded border border-blue-200 text-blue-700 hover:bg-blue-50 cursor-pointer">
                                    수정
                                </button>
                                <button type="button"
                                        wire:click="openDeleteModal({{ $role->id }})"
                                        class="px-2 py-1 text-xs rounded border border-rose-200 text-rose-700 hover:bg-rose-50 cursor-pointer">
                                    삭제
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-14 text-center text-gray-400">
                            <p class="font-medium">등록된 역할이 없습니다.</p>
                            <p class="text-sm mt-1">역할 생성 버튼으로 첫 항목을 추가해 주세요.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($roles->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $roles->links() }}
            </div>
        @endif
    </div>

    @if($showCreateModal)
        <div class="mochi-modal-overlay" wire:key="setup-role-create-modal">
            <div class="mochi-modal-shell max-w-4xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">역할 생성</h3>
                    <button type="button" wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="createRole" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">역할 키</label>
                            <input type="text" wire:model.defer="newRoleKey" placeholder="admin"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @error('newRoleKey') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">역할명</label>
                            <input type="text" wire:model.defer="newRoleName" placeholder="관리자"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @error('newRoleName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">설명</label>
                            <input type="text" wire:model.defer="newDescription"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @error('newDescription') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">활성 여부</label>
                            <select wire:model.defer="newIsActive"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="1">활성</option>
                                <option value="0">비활성</option>
                            </select>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">메뉴</th>
                                @foreach($permissionActions as $action)
                                    <th class="px-2 py-2 text-center font-semibold text-gray-600">{{ strtoupper($action) }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($permissionMenus as $menuKey => $menuLabel)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2 text-gray-700 font-medium">{{ $menuLabel }}</td>
                                    @foreach($permissionActions as $action)
                                        <td class="px-2 py-2 text-center">
                                            <input type="checkbox"
                                                   wire:model.defer="newPermissions.{{ $menuKey }}.{{ $action }}"
                                                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeCreateModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                                wire:loading.attr="disabled"
                                wire:target="createRole">
                            생성
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showEditModal)
        <div class="mochi-modal-overlay" wire:key="setup-role-edit-modal">
            <div class="mochi-modal-shell max-w-4xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">역할 수정</h3>
                    <button type="button" wire:click="closeEditModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="updateRole" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">역할 키</label>
                            <input type="text" wire:model.defer="editRoleKey"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('editRoleKey') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">역할명</label>
                            <input type="text" wire:model.defer="editRoleName"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('editRoleName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">설명</label>
                            <input type="text" wire:model.defer="editDescription"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('editDescription') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">활성 여부</label>
                            <select wire:model.defer="editIsActive"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="1">활성</option>
                                <option value="0">비활성</option>
                            </select>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">메뉴</th>
                                @foreach($permissionActions as $action)
                                    <th class="px-2 py-2 text-center font-semibold text-gray-600">{{ strtoupper($action) }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($permissionMenus as $menuKey => $menuLabel)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2 text-gray-700 font-medium">{{ $menuLabel }}</td>
                                    @foreach($permissionActions as $action)
                                        <td class="px-2 py-2 text-center">
                                            <input type="checkbox"
                                                   wire:model.defer="editPermissions.{{ $menuKey }}.{{ $action }}"
                                                   class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeEditModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                wire:loading.attr="disabled"
                                wire:target="updateRole">
                            저장
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div class="mochi-modal-overlay" wire:key="setup-role-delete-modal">
            <div class="mochi-modal-shell max-w-md">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">역할 삭제 확인</h3>
                </div>
                <div class="px-6 py-5">
                    <p class="text-sm text-gray-700">
                        역할 <span class="font-semibold text-rose-700">{{ $deleteRoleName }}</span> 을(를) 삭제하시겠습니까?
                    </p>
                    @error('deleteId') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" wire:click="closeDeleteModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">
                            취소
                        </button>
                        <button type="button" wire:click="deleteRole"
                                class="px-4 py-2 text-sm bg-rose-600 text-white rounded-lg hover:bg-rose-700"
                                wire:loading.attr="disabled"
                                wire:target="deleteRole">
                            삭제
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

