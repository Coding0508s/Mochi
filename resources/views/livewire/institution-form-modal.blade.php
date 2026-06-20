<div>
    @if($embedMode === 'manager' && $showManagerModal)
        <div class="mochi-modal-overlay"
             wire:click.self="closeManagerModal">
            <div class="mochi-modal-shell max-w-xl"
                 wire:click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">담당자 변경</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $editInstitutionName ?: '-' }} ({{ $editSkCode ?: '-' }})
                        </p>
                    </div>
                    <button wire:click="closeManagerModal" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveManagers" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">담당 CO</label>
                        @if($this->canEditInstitutionDetailCo())
                            <select wire:model="editCo"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                <option value="">미지정</option>
                                @foreach($coManagerOptions as $manager)
                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="py-2 px-3 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg">{{ $editCo ?: '-' }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">담당 Coach</label>
                        @if($this->canEditInstitutionDetailTr())
                            <select wire:model="editTr"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                <option value="">미지정</option>
                                @foreach($trManagerOptions as $manager)
                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="py-2 px-3 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg">{{ $editTr ?: '-' }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">담당 CS</label>
                        @if($this->canEditInstitutionDetailCs())
                            <select wire:model="editCs"
                                    class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
                                <option value="">미지정</option>
                                @foreach($csManagerOptions as $manager)
                                    <option value="{{ $manager }}">{{ $manager }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="py-2 px-3 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg">{{ $editCs ?: '-' }}</p>
                        @endif
                    </div>

                    @error('managerEdit')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button type="button" wire:click="closeManagerModal"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                            취소
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm text-white bg-mochi-header hover:bg-mochi-header/90 rounded-lg transition-colors"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-70 cursor-not-allowed">
                            <span wire:loading.remove wire:target="saveManagers">저장</span>
                            <span wire:loading wire:target="saveManagers">저장 중...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($embedMode === 'detail' && $isEditingDetail)
        @include('partials.institution.form-detail-fields')

        <div class="mt-3 flex items-center justify-end gap-2 border-t border-gray-100 pt-3">
            <button type="button" wire:click="cancelDetailEdit"
                    class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                취소
            </button>
            <button type="button" wire:click="saveDetailFields"
                    class="px-4 py-2 text-sm text-white bg-mochi-header hover:bg-mochi-header/90 rounded-lg transition-colors cursor-pointer"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70 cursor-not-allowed"
                    wire:target="saveDetailFields">
                <span wire:loading.remove wire:target="saveDetailFields">저장</span>
                <span wire:loading wire:target="saveDetailFields">저장 중...</span>
            </button>
        </div>

        @error('detailEdit')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    @endif
</div>
