<div class="mochi-page">
    @if(session('success'))
        <div class="mb-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- 상단 요약 --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-[#2b78c5]">Employees</h2>
            <span class="text-gray-300">|</span>
            <span class="text-gray-600">현재 팀 <span class="font-semibold text-indigo-600">{{ $currentTeamLabel }}</span></span>
            <span class="text-gray-600">전체 <span class="font-semibold text-blue-600">{{ $allCount }}</span></span>
            <span class="text-gray-600">재직 <span class="font-semibold text-green-600">{{ $activeCount }}</span></span>
            <span class="text-gray-600">비활성 <span class="font-semibold text-red-500">{{ $inactiveCount }}</span></span>
            <div class="ml-auto text-gray-500">
                현재 조건 결과: <span class="font-semibold text-gray-700">{{ $employees->total() }}</span>명
            </div>
        </div>
    </div>

    {{-- 필터/검색 --}}
    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-4 text-sm">
                @foreach(['name' => '이름', 'email' => '이메일', 'department' => '부서'] as $value => $label)
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" wire:model.live="searchType" value="{{ $value }}"
                               class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"/>
                        <span class="{{ $searchType === $value ? 'text-blue-600 font-semibold' : 'text-gray-600' }}">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>

            <select wire:model.live="filterStatus"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 상태</option>
                @foreach($statusOptions as $status)
                    <option value="{{ (string) $status }}">
                        {{ (int) $status === 1 ? '재직' : ((int) $status === 0 ? '비활성' : '상태 ' . $status) }}
                    </option>
                @endforeach
            </select>

            <select wire:model.live="filterDept"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 부서</option>
                @foreach($deptOptions as $dept)
                    <option value="{{ $dept->WORKDEPT }}">
                        {{ $dept->dept_name ?: $dept->WORKDEPT }}
                    </option>
                @endforeach
            </select>

            <div class="relative flex-1 min-w-56">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="{{ ['name' => '이름', 'email' => '이메일', 'department' => '부서'][$searchType] }} 검색"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            @if($search || $filterStatus !== '' || $filterDept !== '')
                <button wire:click="$set('search', ''); $set('filterStatus', ''); $set('filterDept', '')"
                        class="py-2 px-3 text-sm text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                    초기화
                </button>
            @endif

            @if($canManageEmployeeDepartment)
                <button type="button"
                   wire:click="openCreateEmployeeModal"
                   class="py-2 px-3 text-sm text-white bg-sky-600 rounded-lg hover:bg-sky-700 cursor-pointer">
                    직원 등록
                </button>
            @endif

            @if($canManageEmployees)
                <button type="button"
                        wire:click="openCreateTeamModal"
                        class="py-2 px-3 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 cursor-pointer">
                    팀 추가
                </button>

                <button type="button"
                        wire:click="openDeleteTeamModal"
                        class="py-2 px-3 text-sm text-white bg-rose-600 rounded-lg hover:bg-rose-700 cursor-pointer">
                    팀 삭제
                </button>
            @endif
        </div>
    </div>

    {{-- 리스트 --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="px-3 py-2 text-left text-xs font-semibold">No</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">
                            <button wire:click="sort('KOREANAME')" class="flex items-center gap-1 hover:text-blue-700">
                                이름 @if($sortField === 'KOREANAME') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">영어이름</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">직책</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">부서명</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">Email</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">연락처</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">입사일</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold">상태</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($employees as $index => $emp)
                        <tr wire:key="emp-row-{{ $emp->EMPNO }}"
                            @if($canManageEmployees) wire:click="openEditModal('{{ $emp->EMPNO }}')" @endif
                            class="mochi-table-row-hover transition-colors {{ $canManageEmployees ? 'cursor-pointer' : 'cursor-default' }}">
                            <td class="px-3 py-2 text-gray-500 text-xs">{{ $employees->firstItem() + $index }}</td>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ $emp->KOREANAME ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $emp->ENGLISHNAME ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $emp->JOB ?? ($emp->{'직책'} ?? '-') }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $emp->DEPARTMENT_NAME ?: ($emp->WORKDEPT ?? '-') }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $emp->EMAIL ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $emp->PHONENO ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $emp->HIREDATE ? \Illuminate\Support\Carbon::parse($emp->HIREDATE)->format('Y-m-d') : '-' }}</td>
                            <td class="px-3 py-2 text-center">
                                @if((int) ($emp->STATUS ?? -1) === 1)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">재직</span>
                                @elseif((int) ($emp->STATUS ?? -1) === 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">비활성</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-16 text-center text-gray-400">
                                <p class="font-medium">직원 데이터가 없습니다</p>
                                <p class="text-sm mt-1">검색/필터 조건을 변경해 보세요.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($employees->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $employees->links() }}
            </div>
        @endif
    </div>

    @if($showCreateEmployeeModal)
        <div class="mochi-modal-overlay" wire:key="employee-create-modal">
            <div class="mochi-modal-shell max-w-3xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">직원 등록</h3>
                    <button type="button"
                            wire:click="closeCreateEmployeeModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="createEmployee" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">사번 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="createEmpNo" maxlength="20"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="예: E2026001"/>
                            @error('createEmpNo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">이름(한글) <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="createKoreanName" maxlength="20"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            @error('createKoreanName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">영어 이름 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="createEnglishName" maxlength="50"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            @error('createEnglishName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">직책 <span class="text-red-500">*</span></label>
                            @if($jobOptions->isEmpty())
                                <input type="text" wire:model.defer="createJob" maxlength="100"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="직책을 입력하세요"/>
                                <p class="mt-1 text-[11px] text-amber-700">기존 직원 데이터가 없어 자유 입력입니다.</p>
                            @else
                                <select wire:model.defer="createJob"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">직책 선택</option>
                                    @foreach($jobOptions as $job)
                                        <option value="{{ $job }}">{{ $job }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('createJob') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">부서(팀) <span class="text-red-500">*</span></label>
                            <select wire:model.defer="createWorkDept"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">부서 선택</option>
                                @foreach($deptOptions as $dept)
                                    <option value="{{ $dept->WORKDEPT }}">
                                        {{ $dept->dept_name ?: $dept->WORKDEPT }}
                                    </option>
                                @endforeach
                            </select>
                            @error('createWorkDept') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">이메일 <span class="text-red-500">*</span></label>
                            <input type="email" wire:model.defer="createEmail" maxlength="100"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            @error('createEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror

                            <p class="mt-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-[11px] text-blue-700">
                                직원 등록 시 로그인 계정이 자동으로 생성되며, 비밀번호 재설정 링크 메일이 발송됩니다.
                            </p>

                            <label class="mt-3 flex items-start gap-2 cursor-pointer select-none">
                                <input type="checkbox" wire:model.defer="createIsGsBrochureAdmin"
                                       class="mt-0.5 rounded border-gray-300 text-[#2b78c5] focus:ring-[#2b78c5]"/>
                                <span class="text-sm text-gray-700 leading-snug">
                                    GS Brochure 관리 권한 부여
                                </span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">연락처 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="createPhone" maxlength="20"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            @error('createPhone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">상태</label>
                            <select wire:model.defer="createStatus"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">미지정</option>
                                <option value="1">재직</option>
                                <option value="0">퇴사</option>
                            </select>
                            @error('createStatus') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">입사일</label>
                            <input type="date" wire:model.defer="createHireDate"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            @error('createHireDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button"
                                wire:click="closeCreateEmployeeModal"
                                class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm text-white bg-[#2b78c5] rounded-lg hover:bg-[#256bb0] cursor-pointer"
                                wire:loading.attr="disabled"
                                wire:target="createEmployee">
                            등록
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showEditModal)
        <div class="mochi-modal-overlay" wire:key="employee-edit-modal">
            <div class="mochi-modal-shell max-w-3xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">직원 정보 수정</h3>
                        <p class="text-xs text-gray-500 mt-1">사번: {{ $editingEmpNo }}</p>
                    </div>
                    <button type="button"
                            wire:click="closeEditModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveEmployee" class="px-6 py-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">이름(한글)</label>
                            <input type="text" wire:model.defer="editKoreanName"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('editKoreanName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">영어이름</label>
                            <input type="text" wire:model.defer="editEnglishName"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('editEnglishName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">직책</label>
                            <select wire:model.defer="editJob"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">직책 선택</option>
                                @foreach($jobOptions as $job)
                                    <option value="{{ $job }}">{{ $job }}</option>
                                @endforeach
                            </select>
                            @error('editJob') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">연락처</label>
                            <input type="text" wire:model.defer="editPhone"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('editPhone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">이메일</label>
                            <input type="email" wire:model.defer="editEmail"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('editEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">상태</label>
                            <select wire:model.defer="editStatus"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">미설정</option>
                                <option value="1">재직</option>
                                <option value="0">비활성</option>
                            </select>
                            @error('editStatus') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        @if($canManageUserAccounts && $isPeopleModalAccountEditEnabled)
                            <div class="md:col-span-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 space-y-3">
                                <div class="text-xs font-semibold text-gray-500">계정 권한</div>

                                <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
                                    계정 활성은 직원 상태와 자동 동기화됩니다.
                                    <span class="ml-1 font-semibold">
                                        {{ $editStatus === '0' ? '현재 비활성(로그인 불가)' : '현재 활성(로그인 가능)' }}
                                    </span>
                                </div>

                                <label class="flex items-start gap-2 cursor-pointer select-none">
                                    <input type="checkbox"
                                           wire:model.defer="editUserIsAdmin"
                                           class="mt-0.5 rounded border-gray-300 text-[#2b78c5] focus:ring-[#2b78c5]"/>
                                    <span class="text-sm text-gray-700 leading-snug">
                                        관리자 권한
                                        <span class="mt-0.5 block text-[11px] font-normal text-gray-500">
                                            Setup, 팀 관리 등 관리자 기능 접근 권한입니다.
                                        </span>
                                    </span>
                                </label>
                                @error('editUserIsAdmin') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror

                                <label class="flex items-start gap-2 cursor-pointer select-none">
                                    <input type="checkbox"
                                           wire:model.defer="editGsBrochureAdmin"
                                           class="mt-0.5 rounded border-gray-300 text-[#2b78c5] focus:ring-[#2b78c5]"/>
                                    <span class="text-sm text-gray-700 leading-snug">
                                        GS Brochure 권한
                                        <span class="mt-0.5 block text-[11px] font-normal text-gray-500">
                                            GS Brochure 관리자 화면 접근 권한입니다.
                                        </span>
                                    </span>
                                </label>

                                <label class="flex items-start gap-2 cursor-pointer select-none">
                                    <input type="checkbox"
                                           wire:model.defer="editCanManageStoreInventory"
                                           class="mt-0.5 rounded border-gray-300 text-[#2b78c5] focus:ring-[#2b78c5]"/>
                                    <span class="text-sm text-gray-700 leading-snug">
                                        스토어 재고 수량 수정
                                        <span class="mt-0.5 block text-[11px] font-normal text-gray-500">
                                            Store 재고 화면에서 품목 추가·스토어사이트 재고 수정 등을 할 수 있습니다.
                                        </span>
                                    </span>
                                </label>
                                @error('editCanManageStoreInventory') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror

                                @if(! $hasLinkedLoginAccount)
                                    <p class="text-[11px] text-amber-700">
                                        연결된 로그인 계정이 없어도 저장 시 현재 이메일로 계정을 자동 생성합니다.
                                    </p>
                                @endif
                            </div>
                        @endif

                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">부서(팀)</label>
                            @can('manageEmployeeDepartment')
                                <select wire:model.defer="editWorkDept"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">부서 선택</option>
                                    @foreach($deptOptions as $dept)
                                        <option value="{{ $dept->WORKDEPT }}">
                                            {{ $dept->dept_name ?: $dept->WORKDEPT }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <select wire:model.defer="editWorkDept"
                                        disabled
                                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-600 cursor-not-allowed">
                                    <option value="">부서 선택</option>
                                    @foreach($deptOptions as $dept)
                                        <option value="{{ $dept->WORKDEPT }}">
                                            {{ $dept->dept_name ?: $dept->WORKDEPT }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-amber-700">부서 변경은 관리자만 할 수 있습니다.</p>
                            @endcan
                            @error('editWorkDept') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2">
                        <button type="button"
                                wire:click="closeEditModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 cursor-pointer">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 cursor-pointer disabled:opacity-60"
                                wire:loading.attr="disabled"
                                wire:target="saveEmployee">
                            저장
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showCreateTeamModal)
        <div class="mochi-modal-overlay" wire:key="team-create-modal">
            <div class="mochi-modal-shell max-w-lg">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">팀 생성</h3>
                    <button type="button"
                            wire:click="closeCreateTeamModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="createTeam" class="px-6 py-5">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">팀명 (DEPTNAME)</label>
                            <input type="text"
                                   wire:model.defer="newDeptName"
                                   maxlength="25"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            @error('newDeptName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <p class="text-xs text-indigo-600">
                            팀코드(DEPTNO)는 A01 형식으로 자동 생성됩니다.
                        </p>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2">
                        <button type="button"
                                wire:click="closeCreateTeamModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 cursor-pointer">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 cursor-pointer disabled:opacity-60"
                                wire:loading.attr="disabled"
                                wire:target="createTeam">
                            생성
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showDeleteTeamModal)
        <div class="mochi-modal-overlay" wire:key="team-delete-modal">
            <div class="mochi-modal-shell max-w-lg">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">팀 삭제</h3>
                    <button type="button"
                            wire:click="closeDeleteTeamModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="deleteTeam" class="px-6 py-5">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">삭제할 팀 선택</label>
                            <select wire:model.defer="deleteDeptNo"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-500">
                                <option value="">팀 선택</option>
                                @foreach($deptOptions as $dept)
                                    <option value="{{ $dept->WORKDEPT }}">
                                        {{ $dept->dept_name ?: $dept->WORKDEPT }}
                                    </option>
                                @endforeach
                            </select>
                            @error('deleteDeptNo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <p class="text-xs text-rose-600">
                            팀에 소속된 직원이 1명 이상 있으면 삭제되지 않습니다.
                        </p>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2">
                        <button type="button"
                                wire:click="closeDeleteTeamModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 cursor-pointer">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm bg-rose-600 text-white rounded-lg hover:bg-rose-700 cursor-pointer disabled:opacity-60"
                                wire:loading.attr="disabled"
                                wire:target="deleteTeam">
                            삭제
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

