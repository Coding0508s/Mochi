<div class="mochi-page">
    @if(session('success'))
        <div class="mb-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 flex items-center gap-2" data-mochi-flash-dismiss="3000" role="status">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 flex items-center gap-2" data-mochi-flash-dismiss="3000" role="alert">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- 상단 요약 --}}
    <div class="mochi-summary-card">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <h2 class="text-base font-semibold text-mochi-header">Employees</h2>
            <span class="text-gray-300">|</span>
            <span class="text-gray-600">현재 팀 <span class="font-semibold text-indigo-600">{{ $currentTeamLabel }}</span></span>
            <span class="text-gray-600">전체 <span class="font-semibold text-blue-600">{{ $allCount }}</span></span>
            <span class="text-gray-600">재직 <span class="font-semibold text-green-600">{{ $activeCount }}</span></span>
            <span class="text-gray-600">비활성 <span class="font-semibold text-gray-600">{{ $inactiveCount }}</span></span>
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
                               class="w-4 h-4 text-mochi-header border-gray-300 focus:ring-mochi-header"/>
                        <span class="{{ $searchType === $value ? 'text-mochi-header font-semibold' : 'text-gray-600' }}">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>

            <select wire:model.live="filterStatus"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                <option value="">전체 상태</option>
                @foreach($statusOptions as $status)
                    <option value="{{ (string) $status }}">
                        {{ (int) $status === 1 ? '재직' : ((int) $status === 0 ? '비활성' : '상태 ' . $status) }}
                    </option>
                @endforeach
            </select>

            <select wire:model.live="filterDept"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
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
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header" />
            </div>

            @if($search || $filterStatus !== '1' || $filterDept !== '')
                <button wire:click="$set('search', ''); $set('filterStatus', '1'); $set('filterDept', '')"
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
            @endif

            @can('deleteTeamStructure')
                <button type="button"
                        wire:click="openDeleteTeamModal"
                        class="py-2 px-3 text-sm text-white bg-rose-600 rounded-lg hover:bg-rose-700 cursor-pointer">
                    팀 삭제
                </button>
            @endcan
        </div>

        @if($canManageEmployeeDepartment)
            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
                <input type="file"
                       wire:model="importFile"
                       accept=".xls,.xlsx"
                       class="w-full md:w-auto rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white">
                <button type="button"
                        wire:click="previewEmployeeImport"
                        wire:loading.attr="disabled"
                        wire:target="previewEmployeeImport,importFile"
                        class="px-3 py-2 text-sm rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50 disabled:opacity-60">
                    미리보기
                </button>
                @if($importPreview !== null)
                    <button type="button"
                            wire:click="applyEmployeeImport"
                            wire:loading.attr="disabled"
                            wire:target="applyEmployeeImport"
                            class="px-3 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-60">
                        적용
                    </button>
                    <button type="button"
                            wire:click="cancelEmployeeImport"
                            class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
                        취소
                    </button>
                @endif
                @if($hasLastEmployeeImportRollback)
                    <button type="button"
                            wire:click="openImportResetModal"
                            class="px-3 py-2 text-sm rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50">
                        엑셀 초기화
                    </button>
                @endif
            </div>
            @error('importFile') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            @error('importResetConfirmationText') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

            @if($hasLastEmployeeImportRollback && $lastEmployeeImportRollbackSummary !== null)
                <p class="mt-2 text-xs text-rose-700">
                    마지막 엑셀 반영 되돌리기 가능 — {{ $lastEmployeeImportRollbackSummary }}
                </p>
            @endif

            @if($importNotice !== null || $importPreview !== null || $importErrors !== [])
                <div class="mt-3 space-y-2">
                    @if($importNotice !== null)
                        <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm text-indigo-800" role="status">
                            {{ $importNotice }}
                        </div>
                    @endif

                    @if($importPreview !== null)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
                            미리보기 결과 — 신규 {{ $importPreview['inserted'] }}건 · 수정 {{ $importPreview['updated'] }}건 · 재활성 {{ $importPreview['reactivated'] }}건 · 숨김 {{ $importPreview['hidden'] }}건 · 신규 부서 {{ $importPreview['departments_created'] }}건 · 건너뜀 {{ $importPreview['skipped'] }}건
                            <span class="block mt-1 text-xs text-amber-800">[적용]을 눌러야 DB에 반영됩니다.</span>
                        </div>
                    @endif

                    @if($importErrors !== [])
                        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 space-y-1" role="alert">
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
        @endif
    </div>

    {{-- 리스트 --}}
    <div class="mochi-table-card">
        <div class="overflow-x-auto isolate">
            <table class="w-full min-w-[920px] text-sm whitespace-nowrap">
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
                        @if($canManageUserAccounts)
                            <th class="px-3 py-2 text-center text-xs font-semibold">계정</th>
                        @endif
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
                            <td class="px-3 py-2 text-gray-700">{{ \App\Support\EmployeeHireDate::formatDisplay($emp->HIREDATE) }}</td>
                            <td class="px-3 py-2 text-center">
                                @if((int) ($emp->STATUS ?? -1) === 1)
                                    <span class="text-xs text-green-700">재직</span>
                                @elseif((int) ($emp->STATUS ?? -1) === 0)
                                    <span class="text-xs text-gray-600">비활성</span>
                                @else
                                    <span class="text-xs text-gray-600">-</span>
                                @endif
                            </td>
                            @if($canManageUserAccounts)
                                {{-- 행 클릭(수정 모달 열기)과 충돌 방지를 위해 wire:click.stop 사용 --}}
                                <td class="px-3 py-2 text-center">
                                    @php
                                        $linkedId = $emp->linked_user_id ?? null;
                                        $linkedActive = $emp->linked_user_is_active ?? null;
                                        $isEmployeeActive = (int) ($emp->STATUS ?? -1) === 1;
                                        $employeeEmail = trim((string) ($emp->EMAIL ?? ''));
                                    @endphp

                                    @if($linkedId !== null && $linkedActive === true)
                                        <button type="button"
                                                wire:click.stop="openSendResetModal('{{ $emp->EMPNO }}')"
                                                title="비밀번호 재설정 메일 보내기"
                                                aria-label="비밀번호 재설정 메일 보내기"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-mochi-header hover:bg-blue-50 cursor-pointer">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                    @elseif($linkedId === null && $isEmployeeActive && $employeeEmail !== '')
                                        <button type="button"
                                                wire:click.stop="openSendResetModal('{{ $emp->EMPNO }}')"
                                                title="계정 발급 + 비밀번호 설정 메일 보내기"
                                                aria-label="계정 발급 + 비밀번호 설정 메일 보내기"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-emerald-600 hover:bg-emerald-50 cursor-pointer">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm4 8v-1a4 4 0 00-3-3.87M4 20v-1a4 4 0 013-3.87M19 8v6m3-3h-6"/>
                                            </svg>
                                        </button>
                                    @elseif($linkedId !== null && $linkedActive === false)
                                        <span title="비활성 계정 (활성 후 발송 가능)"
                                              class="text-[10px] text-gray-500">
                                            비활성
                                        </span>
                                    @elseif($linkedId === null && ! $isEmployeeActive)
                                        <span title="재직 중이 아닌 직원은 계정을 만들 수 없습니다"
                                              class="text-[10px] text-gray-500">
                                            불가
                                        </span>
                                    @elseif($linkedId === null && $employeeEmail === '')
                                        <span title="이메일이 비어 있어 계정을 만들 수 없습니다"
                                              class="text-[10px] text-gray-600">
                                            이메일 없음
                                        </span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManageUserAccounts ? 10 : 9 }}" class="px-4 py-16 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
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
            <div class="mochi-modal-shell max-w-3xl max-h-[90vh] min-h-0 flex flex-col">
                <x-admin.modal-header
                    title="직원 등록"
                    close-action="closeCreateEmployeeModal"
                />

                <form wire:submit.prevent="createEmployee" class="flex min-h-0 flex-1 flex-col">
                    <div class="mochi-modal-body-scroll flex-1 px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">사번 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="createEmpNo" maxlength="20"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"
                                   placeholder="예: E2026001"/>
                            @error('createEmpNo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">이름(한글) <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="createKoreanName" maxlength="20"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"/>
                            @error('createKoreanName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">영어 이름 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="createEnglishName" maxlength="50"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"/>
                            @error('createEnglishName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">직책 <span class="text-red-500">*</span></label>
                            @if($jobOptions->isEmpty())
                                <input type="text" wire:model.defer="createJob" maxlength="100"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"
                                       placeholder="직책을 입력하세요"/>
                                <p class="mt-1 text-[11px] text-amber-700">기존 직원 데이터가 없어 자유 입력입니다.</p>
                            @else
                                <select wire:model.defer="createJob"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
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
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
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
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"/>
                            @error('createEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror

                            <p class="mt-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-[11px] text-blue-700">
                                직원 등록 시 로그인 계정이 자동으로 생성되며, 비밀번호 재설정 링크 메일이 발송됩니다.
                                특수 권한은 등록 후 <a href="{{ route('setup.roles') }}" class="underline font-medium">Setup &gt; 역할·권한</a>에서 역할로 할당하세요.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">연락처 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="createPhone" maxlength="20"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"/>
                            @error('createPhone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">상태</label>
                            <select wire:model.defer="createStatus"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                <option value="">미지정</option>
                                <option value="1">재직</option>
                                <option value="0">퇴사</option>
                            </select>
                            @error('createStatus') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">입사일</label>
                            <input type="date" wire:model.defer="createHireDate"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"/>
                            @error('createHireDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">성별</label>
                            <select wire:model.defer="createSex"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                @foreach(\App\Support\EmployeeSex::options() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('createSex') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="shrink-0 flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <button type="button"
                                wire:click="closeCreateEmployeeModal"
                                class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 cursor-pointer">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm text-white bg-mochi-header rounded-lg hover:bg-mochi-header/90 cursor-pointer disabled:opacity-60"
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
            <div class="mochi-modal-shell max-w-3xl max-h-[90vh] min-h-0 flex flex-col">
                <x-admin.modal-header
                    title="직원 정보 수정"
                    subtitle="사번: {{ $editingEmpNo }}"
                    close-action="closeEditModal"
                />

                <form wire:submit.prevent="saveEmployee" class="flex min-h-0 flex-1 flex-col">
                    <div class="mochi-modal-body-scroll flex-1 px-6 py-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">이름(한글)</label>
                            <input type="text" wire:model.defer="editKoreanName"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            @error('editKoreanName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">영어이름</label>
                            <input type="text" wire:model.defer="editEnglishName"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            @error('editEnglishName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">직책</label>
                            <select wire:model.defer="editJob"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
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
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            @error('editPhone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">이메일</label>
                            <input type="email" wire:model.defer="editEmail"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            @error('editEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">상태</label>
                            <select wire:model.defer="editStatus"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                <option value="">미설정</option>
                                <option value="1">재직</option>
                                <option value="0">비활성</option>
                            </select>
                            @error('editStatus') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        @if($canManageUserAccounts && $isPeopleModalAccountEditEnabled)
                            <div class="md:col-span-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 space-y-3">
                                <div class="text-xs font-semibold text-gray-500">로그인 계정</div>

                                <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
                                    계정 활성은 직원 상태와 자동 동기화됩니다.
                                    <span class="ml-1 font-semibold">
                                        {{ $editStatus === '0' ? '현재 비활성(로그인 불가)' : '현재 활성(로그인 가능)' }}
                                    </span>
                                </div>

                                <p class="text-[11px] text-gray-600 leading-snug">
                                    관리자·GS Brochure·스토어 재고 등 특수 권한은
                                    <a href="{{ route('setup.roles') }}" class="text-mochi-header underline font-medium">Setup &gt; 역할·권한</a>에서
                                    역할로 정의하고 사용자에게 할당하세요.
                                </p>

                                <p class="text-sm text-gray-700">
                                    현재 역할: <span class="font-medium">{{ $editAssignedRoleLabel }}</span>
                                </p>

                                @if(! $hasLinkedLoginAccount)
                                    <p class="text-[11px] text-amber-700">
                                        연결된 로그인 계정이 없어도 저장 시 현재 이메일로 계정을 자동 생성합니다.
                                    </p>
                                @endif

                                {{-- 비밀번호 재설정 메일 발송 영역: 4가지 상태별 분기 --}}
                                <div class="pt-3 border-t border-gray-200">
                                    @if($hasLinkedLoginAccount && $editUserIsActive)
                                        <button type="button"
                                                wire:click="openSendResetModalFromEdit"
                                                class="inline-flex items-center gap-2 px-3 py-2 text-sm text-mochi-header border border-mochi-header rounded-lg hover:bg-blue-50 cursor-pointer">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                            비밀번호 재설정 메일 보내기
                                        </button>
                                        <p class="mt-1.5 text-[11px] text-gray-500">
                                            DB에 등록된 이메일로 발송됩니다. (수정 중인 이메일이 아닌 저장된 이메일)
                                        </p>
                                        <div class="mt-3">
                                            <button type="button"
                                                    wire:click="openTempPasswordModalFromEdit"
                                                    class="inline-flex items-center gap-2 px-3 py-2 text-sm text-amber-800 border border-amber-500 rounded-lg hover:bg-amber-50 cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                                </svg>
                                                임시 비밀번호 발급
                                            </button>
                                            <p class="mt-1.5 text-[11px] text-gray-500">
                                                즉시 로그인이 필요할 때 사용합니다. 발급된 비밀번호는 한 번만 표시되며, 사용자는 다음 로그인 시 새 비밀번호로 변경해야 합니다.
                                            </p>
                                        </div>
                                    @elseif($hasLinkedLoginAccount && ! $editUserIsActive)
                                        <p class="text-[11px] text-amber-700">
                                            비활성 계정에는 비밀번호 재설정 메일을 보낼 수 없습니다. 먼저 계정을 활성화한 뒤 발송해 주세요.
                                        </p>
                                    @elseif(! $hasLinkedLoginAccount && $editStatus === '1' && trim((string) $editEmail) !== '')
                                        <button type="button"
                                                wire:click="openSendResetModalFromEdit"
                                                class="inline-flex items-center gap-2 px-3 py-2 text-sm text-emerald-700 border border-emerald-600 rounded-lg hover:bg-emerald-50 cursor-pointer">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm4 8v-1a4 4 0 00-3-3.87M4 20v-1a4 4 0 013-3.87M19 8v6m3-3h-6"/>
                                            </svg>
                                            계정 발급 + 비밀번호 설정 메일 보내기
                                        </button>
                                        <p class="mt-1.5 text-[11px] text-amber-700">
                                            새 계정은 일반 권한으로 생성됩니다. 추가 권한이 필요하면 발급 후
                                            <a href="{{ route('setup.roles') }}" class="underline font-medium">Setup &gt; 역할·권한</a>에서 역할을 할당하세요.
                                        </p>
                                    @elseif(! $hasLinkedLoginAccount)
                                        <p class="text-[11px] text-amber-700">
                                            재직 중이 아니거나 이메일이 비어 있어 계정을 만들 수 없습니다.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">부서(팀)</label>
                            @can('manageEmployeeDepartment')
                                <select wire:model.defer="editWorkDept"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
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
                    </div>

                    <div class="shrink-0 flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <button type="button"
                                wire:click="closeEditModal"
                                class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 cursor-pointer">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm bg-mochi-header text-white rounded-lg hover:bg-mochi-header/90 cursor-pointer disabled:opacity-60"
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
                <x-admin.modal-header
                    title="새 팀 추가"
                    close-action="closeCreateTeamModal"
                />

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
                <x-admin.modal-header
                    title="팀 삭제 확인"
                    close-action="closeDeleteTeamModal"
                />

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
                        <p class="text-xs text-gray-600">
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

    @if($showSendResetModal)
        <div class="mochi-modal-overlay" wire:key="password-reset-confirm-modal">
            <div class="mochi-modal-shell max-w-lg">
                <x-admin.modal-header
                    title="
                        @if($resetTargetMode === 'create_and_send')
                            계정 발급 + 비밀번호 설정 메일 보내기
                        @else
                            비밀번호 재설정 메일 보내기
                        @endif
                    "
                    close-action="closeSendResetModal"
                />

                <div class="px-6 py-5 space-y-4">
                    @if($resetTargetMode === 'create_and_send')
                        <p class="text-sm text-gray-700">
                            다음 직원의 <strong class="text-emerald-700">로그인 계정을 새로 만들고</strong>
                            비밀번호 설정 메일을 보냅니다.
                        </p>
                    @else
                        <p class="text-sm text-gray-700">
                            다음 직원에게 비밀번호 재설정 메일을 보냅니다.
                        </p>
                    @endif

                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm space-y-1">
                        <div>
                            <span class="text-gray-500">이름</span>
                            <span class="ml-2 font-medium text-gray-900">{{ $resetTargetName ?: '-' }}</span>
                            <span class="ml-2 text-xs text-gray-400">(사번 {{ $resetTargetEmpNo }})</span>
                        </div>
                        <div>
                            <span class="text-gray-500">이메일</span>
                            <span class="ml-2 font-medium text-gray-900">{{ $resetTargetEmail }}</span>
                        </div>
                    </div>

                    @if($resetTargetMode === 'create_and_send')
                        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[12px] text-amber-800 leading-snug">
                            <strong>새 계정은 일반 권한(관리자·GS·재고 권한 모두 없음)으로 생성됩니다.</strong><br/>
                            추가 권한이 필요하면 발급 후 Setup &gt; 역할·권한에서 역할을 할당해 주세요.
                        </div>
                    @endif
                </div>

                <div class="px-6 pb-5 flex items-center justify-end gap-2">
                    <button type="button"
                            wire:click="closeSendResetModal"
                            class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 cursor-pointer">
                        취소
                    </button>
                    <button type="button"
                            wire:click="sendPasswordResetLink"
                            wire:loading.attr="disabled"
                            wire:target="sendPasswordResetLink"
                            class="px-4 py-2 text-sm text-white {{ $resetTargetMode === 'create_and_send' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-mochi-header hover:bg-mochi-header/90' }} rounded-lg cursor-pointer disabled:opacity-60">
                        @if($resetTargetMode === 'create_and_send')
                            계정 발급 + 발송
                        @else
                            발송
                        @endif
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showTempPasswordConfirmModal)
        <div class="mochi-modal-overlay" wire:key="temp-password-confirm-modal">
            <div class="mochi-modal-shell max-w-lg">
                <x-admin.modal-header
                    title="임시 비밀번호 발급"
                    close-action="closeTempPasswordConfirmModal"
                />

                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-gray-700">
                        다음 직원의 로그인 비밀번호를 <strong class="text-amber-800">임시로 재설정</strong>합니다.
                        발급 후 비밀번호는 <strong>이 화면에서 한 번만</strong> 확인할 수 있습니다.
                    </p>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm space-y-1">
                        <div>
                            <span class="text-gray-500">이름</span>
                            <span class="ml-2 font-medium text-gray-900">{{ $tempPasswordTargetName ?: '-' }}</span>
                            <span class="ml-2 text-xs text-gray-400">(사번 {{ $tempPasswordTargetEmpNo }})</span>
                        </div>
                        <div>
                            <span class="text-gray-500">이메일</span>
                            <span class="ml-2 font-medium text-gray-900">{{ $tempPasswordTargetEmail }}</span>
                        </div>
                    </div>

                    <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[12px] text-amber-800 leading-snug">
                        임시 비밀번호는 관리자가 사용자에게 <strong>직접 전달</strong>해야 합니다.
                        시스템은 임시 비밀번호를 메일로 보내지 않습니다.
                        사용자는 다음 로그인 시 <strong>새 비밀번호로 변경</strong>해야 합니다.
                    </div>

                    @if($tempPasswordTargetIsPrivileged)
                        <label class="flex items-start gap-2 cursor-pointer select-none">
                            <input type="checkbox"
                                   wire:model="tempPasswordPrivilegedConfirm"
                                   class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500"/>
                            <span class="text-sm text-gray-700 leading-snug">
                                이 계정은 관리자 권한을 가지고 있습니다. 임시 비밀번호 발급을 진행합니다.
                            </span>
                        </label>
                        @error('tempPasswordPrivilegedConfirm') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                    @endif
                </div>

                <div class="px-6 pb-5 flex items-center justify-end gap-2">
                    <button type="button"
                            wire:click="closeTempPasswordConfirmModal"
                            class="px-4 py-2 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 cursor-pointer">
                        취소
                    </button>
                    <button type="button"
                            wire:click="issueTemporaryPassword"
                            wire:loading.attr="disabled"
                            wire:target="issueTemporaryPassword"
                            @disabled($tempPasswordTargetIsPrivileged && ! $tempPasswordPrivilegedConfirm)
                            class="px-4 py-2 text-sm text-white bg-amber-600 hover:bg-amber-700 rounded-lg cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                        발급
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showTempPasswordResultModal && $issuedTempPassword !== '')
        <div class="mochi-modal-overlay" wire:key="temp-password-result-modal">
            <div class="mochi-modal-shell max-w-lg">
                <x-admin.modal-header
                    title="임시 비밀번호가 발급되었습니다"
                    close-action="closeTempPasswordResultModal"
                />

                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-gray-700">
                        아래 비밀번호를 사용자에게 전달한 뒤, 이 창을 닫아 주세요.
                        <strong class="text-amber-800">닫은 후에는 다시 확인할 수 없습니다.</strong>
                    </p>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-xs text-amber-800 mb-1">임시 비밀번호</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 text-base font-mono font-semibold text-gray-900 tracking-wide select-all">{{ $issuedTempPassword }}</code>
                            <button type="button"
                                    x-data
                                    x-on:click="navigator.clipboard.writeText(@js($issuedTempPassword))"
                                    class="shrink-0 px-2 py-1 text-xs border border-amber-400 text-amber-800 rounded hover:bg-amber-100 cursor-pointer">
                                복사
                            </button>
                        </div>
                    </div>

                    <p class="text-[11px] text-gray-500">
                        사용자 이메일: {{ $tempPasswordTargetEmail }}
                    </p>
                </div>

                <div class="px-6 pb-5 flex items-center justify-end">
                    <button type="button"
                            wire:click="closeTempPasswordResultModal"
                            class="px-4 py-2 text-sm text-white bg-mochi-header hover:bg-mochi-header/90 rounded-lg cursor-pointer">
                        확인 (닫기)
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showImportResetModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4" wire:click.self="closeImportResetModal">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">엑셀 반영 초기화</h3>
                    <button type="button" wire:click="closeImportResetModal" class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">✕</button>
                </div>

                <div class="space-y-4 px-6 py-5">
                    <p class="text-sm text-rose-700">
                        마지막 엑셀 [적용]으로 추가·수정·숨김·생성된 부서를 되돌립니다.
                        @if($lastEmployeeImportRollbackSummary !== null)
                            <span class="block mt-2 text-xs text-rose-800">대상: {{ $lastEmployeeImportRollbackSummary }}</span>
                        @endif
                    </p>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">확인 문구 입력</label>
                        <input type="text"
                               wire:model.defer="importResetConfirmationText"
                               placeholder="엑셀 초기화"
                               class="w-full rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm">
                        @error('importResetConfirmationText') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4">
                        <button type="button" wire:click="closeImportResetModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">취소</button>
                        <button type="button"
                                wire:click="resetLastEmployeeImport"
                                wire:confirm="정말 마지막 엑셀 반영을 되돌릴까요? 신규 직원·계정은 삭제됩니다."
                                class="rounded-lg border border-rose-300 px-4 py-2 text-sm text-rose-700 hover:bg-rose-50">
                            엑셀 초기화 실행
                        </button>
                    </div>
                </div>
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

