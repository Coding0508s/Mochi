<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-5">
    @if (session()->has('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" data-mochi-flash-dismiss="3000" role="status">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" data-mochi-flash-dismiss="3000" role="status">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 mb-1">사번 <span class="text-red-500">*</span></label>
                <input type="text" wire:model.defer="empNo" maxlength="20"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="예: E2026001"/>
                @error('empNo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">이름(한글) <span class="text-red-500">*</span></label>
                <input type="text" wire:model.defer="koreanName" maxlength="20"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                @error('koreanName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">영어 이름 <span class="text-red-500">*</span></label>
                <input type="text" wire:model.defer="englishName" maxlength="50"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                @error('englishName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">직책 <span class="text-red-500">*</span></label>
                @if($jobOptions->isEmpty())
                    <input type="text" wire:model.defer="job" maxlength="100"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="직책을 입력하세요"/>
                    <p class="mt-1 text-[11px] text-amber-700">기존 직원 데이터가 없어 자유 입력입니다. 등록 후 People에서 목록이 채워집니다.</p>
                @else
                    <select wire:model.defer="job"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">직책 선택</option>
                        @foreach($jobOptions as $j)
                            <option value="{{ $j }}">{{ $j }}</option>
                        @endforeach
                    </select>
                @endif
                @error('job') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">부서(팀) <span class="text-red-500">*</span></label>
                <select wire:model.defer="workDept"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">부서 선택</option>
                    @foreach($deptOptions as $dept)
                        <option value="{{ $dept->WORKDEPT }}">
                            {{ $dept->dept_name ?: $dept->WORKDEPT }}
                        </option>
                    @endforeach
                </select>
                @error('workDept') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 mb-1">이메일 <span class="text-red-500">*</span></label>
                <input type="email" wire:model.defer="email" maxlength="100"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                @error('email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror

                <p class="mt-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-[11px] text-blue-700">
                    직원 등록 시 로그인 계정이 자동으로 생성되며, 비밀번호 재설정 링크 메일이 발송됩니다.
                    특수 권한은 등록 후 <a href="{{ route('setup.roles') }}" class="underline font-medium">Setup &gt; 역할·권한</a>에서 역할로 할당하세요.
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">연락처 <span class="text-red-500">*</span></label>
                <input type="text" wire:model.defer="phone" maxlength="20"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                @error('phone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">상태</label>
                <select wire:model.defer="status"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">미지정</option>
                    <option value="1">재직</option>
                    <option value="0">퇴사</option>
                </select>
                @error('status') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">입사일</label>
                <input type="date" wire:model.defer="hireDate"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                @error('hireDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">성별</label>
                <select wire:model.defer="sex"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach(\App\Support\EmployeeSex::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('sex') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('setup.index') }}"
               class="py-2 px-4 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                취소
            </a>
            <button type="submit"
                    class="py-2 px-4 text-sm text-white bg-mochi-header rounded-lg hover:bg-mochi-header/90 cursor-pointer">
                등록
            </button>
        </div>
    </form>
</div>
