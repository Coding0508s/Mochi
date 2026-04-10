<div class="mochi-page">
    <div class="mb-6 flex flex-wrap items-center gap-4">
        <a href="{{ route('institutions.index') }}"
           class="text-sm text-blue-600 hover:text-blue-800 font-medium">
            ← 기관리스트로 돌아가기
        </a>
    </div>

    <div class="mochi-filter-card">
        <h1 class="text-lg font-bold text-gray-900 mb-4">신규 기관 생성</h1>

        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">기관명 <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="newInstitutionName"
                           class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('newInstitutionName') ? 'border-red-400' : 'border-gray-300' }}"
                           placeholder="기관명을 입력하세요" />
                    @error('newInstitutionName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SK코드 <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="newSkCode"
                           class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('newSkCode') ? 'border-red-400' : 'border-gray-300' }}"
                           placeholder="예: SK9999" />
                    @error('newSkCode') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">구분</label>
                    <select wire:model="newGubun"
                            class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">미지정</option>
                        @foreach($gubunList as $gubun)
                            <option value="{{ $gubun }}">{{ $gubun }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">가능성</label>
                    <select wire:model="newPossibility"
                            class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">미선택</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                    @error('newPossibility') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">원장명</label>
                    <input type="text" wire:model="newDirector"
                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="원장명" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer Type</label>
                    <input type="text" wire:model="newCustomerType"
                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="예: GTS 15 전환" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">GS Number</label>
                    <input type="text" wire:model="newGsNo"
                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="예: 31" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">대표전화</label>
                    <input type="text" wire:model="newPhone"
                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="대표 전화번호" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">직통 연락처</label>
                    <input type="text" wire:model="newAccountTel"
                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="직통 연락처" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                <input type="text" wire:model="newAddress"
                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="기관 주소" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">담당 CO</label>
                    <select wire:model="newCo"
                            class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">미지정</option>
                        @foreach($coManagerOptions as $manager)
                            <option value="{{ $manager }}">{{ $manager }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">담당 TR</label>
                    <select wire:model="newTr"
                            class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">미지정</option>
                        @foreach($trManagerOptions as $manager)
                            <option value="{{ $manager }}">{{ $manager }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">담당 CS</label>
                    <select wire:model="newCs"
                            class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">미지정</option>
                        @foreach($csManagerOptions as $manager)
                            <option value="{{ $manager }}">{{ $manager }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-gray-200">
                <a href="{{ route('institutions.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                    취소
                </a>
                <button type="submit"
                        class="px-5 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-70 cursor-not-allowed">
                    <span wire:loading.remove wire:target="save">저장</span>
                    <span wire:loading wire:target="save">저장 중...</span>
                </button>
            </div>
        </form>
    </div>
</div>
